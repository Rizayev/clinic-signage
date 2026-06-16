<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class MediaService
{
    /**
     * Store an uploaded file and create a Media row with derived metadata.
     */
    public function handleUpload(UploadedFile $file, array $meta = []): Media
    {
        $originalName = $file->getClientOriginalName();

        $path = $file->store('media', 'public');

        $attributes = $this->deriveMetadata($path, $file);

        $media = new Media();
        $media->title = $meta['title'] ?? pathinfo($originalName, PATHINFO_FILENAME) ?: $originalName;
        $media->category = $meta['category'] ?? null;
        $media->file_path = $path;
        $media->status = 'active';
        $media->created_by = Auth::id();
        $media->fill($attributes);
        $media->save();

        return $media;
    }

    /**
     * Replace the stored file for an existing Media, re-deriving metadata.
     */
    public function replaceFile(Media $media, UploadedFile $file): Media
    {
        $oldPath = $media->file_path;
        $oldThumb = $media->thumbnail_path;

        $path = $file->store('media', 'public');

        $attributes = $this->deriveMetadata($path, $file);

        // Reset metadata that may not be re-populated for the new file type.
        $media->thumbnail_path = null;
        $media->duration = null;
        $media->width = null;
        $media->height = null;

        $media->file_path = $path;
        $media->fill($attributes);
        $media->save();

        $disk = Storage::disk('public');
        if ($oldPath && $oldPath !== $path && $disk->exists($oldPath)) {
            $disk->delete($oldPath);
        }
        if ($oldThumb && $oldThumb !== $media->thumbnail_path && $disk->exists($oldThumb)) {
            $disk->delete($oldThumb);
        }

        return $media;
    }

    /**
     * Derive type/mime/size/checksum/dimensions/duration/thumbnail for a stored file.
     *
     * @return array<string, mixed>
     */
    protected function deriveMetadata(string $path, UploadedFile $file): array
    {
        $disk = Storage::disk('public');
        $absolute = $disk->path($path);

        $mime = $file->getMimeType() ?: $disk->mimeType($path) ?: null;
        $size = $disk->exists($path) ? $disk->size($path) : null;

        $checksum = null;
        if (is_file($absolute)) {
            $hash = @hash_file('sha256', $absolute);
            $checksum = $hash !== false ? $hash : null;
        }

        $type = $this->resolveType($mime, $file);

        $attributes = [
            'mime_type' => $mime,
            'size' => $size,
            'checksum' => $checksum,
            'type' => $type,
        ];

        if ($type === 'image') {
            $attributes += $this->imageMetadata($absolute);
        } elseif ($type === 'video') {
            $attributes += $this->videoMetadata($absolute, $path);
        }

        return $attributes;
    }

    /**
     * Map a mime type / extension to a Media.type value.
     */
    protected function resolveType(?string $mime, UploadedFile $file): string
    {
        if ($mime) {
            if (str_starts_with($mime, 'video/')) {
                return 'video';
            }
            if (str_starts_with($mime, 'image/')) {
                return 'image';
            }
            if (str_starts_with($mime, 'audio/')) {
                return 'audio';
            }
            if ($mime === 'text/html') {
                return 'html';
            }
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        return match ($ext) {
            'mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v' => 'video',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg' => 'image',
            'mp3', 'wav', 'ogg', 'aac', 'flac', 'm4a' => 'audio',
            'html', 'htm' => 'html',
            default => $ext !== '' ? $ext : 'file',
        };
    }

    /**
     * @return array<string, int>
     */
    protected function imageMetadata(string $absolute): array
    {
        $out = [];

        try {
            if (is_file($absolute)) {
                $info = @getimagesize($absolute);
                if (is_array($info)) {
                    $out['width'] = (int) ($info[0] ?? 0) ?: null;
                    $out['height'] = (int) ($info[1] ?? 0) ?: null;
                }
            }
        } catch (\Throwable $e) {
            // Degrade gracefully — leave dimensions null.
        }

        return array_filter($out, fn ($v) => $v !== null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function videoMetadata(string $absolute, string $path): array
    {
        $out = [];

        // ffprobe: duration + dimensions.
        try {
            $process = new Process([
                'ffprobe',
                '-v', 'error',
                '-select_streams', 'v:0',
                '-show_entries', 'stream=width,height:format=duration',
                '-of', 'json',
                $absolute,
            ]);
            $process->setTimeout(60);
            $process->run();

            if ($process->isSuccessful()) {
                $data = json_decode($process->getOutput(), true);
                if (is_array($data)) {
                    $duration = $data['format']['duration'] ?? null;
                    if ($duration !== null && is_numeric($duration)) {
                        $out['duration'] = (int) round((float) $duration);
                    }

                    $stream = $data['streams'][0] ?? null;
                    if (is_array($stream)) {
                        if (isset($stream['width']) && (int) $stream['width'] > 0) {
                            $out['width'] = (int) $stream['width'];
                        }
                        if (isset($stream['height']) && (int) $stream['height'] > 0) {
                            $out['height'] = (int) $stream['height'];
                        }
                    }
                }
            }
        } catch (ProcessFailedException $e) {
            // ffprobe missing/failed — leave duration & dimensions null.
        } catch (\Throwable $e) {
            // ffprobe binary not found or other failure — degrade gracefully.
        }

        // ffmpeg: thumbnail at 1s.
        $thumbnail = $this->generateThumbnail($absolute, $path);
        if ($thumbnail !== null) {
            $out['thumbnail_path'] = $thumbnail;
        }

        return $out;
    }

    /**
     * Generate a jpg thumbnail at ~1s into media/thumbs/. Returns relative path or null.
     */
    protected function generateThumbnail(string $absolute, string $path): ?string
    {
        try {
            $disk = Storage::disk('public');
            $thumbRelative = 'media/thumbs/' . pathinfo($path, PATHINFO_FILENAME) . '.jpg';
            $thumbAbsolute = $disk->path($thumbRelative);

            $dir = dirname($thumbAbsolute);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $process = new Process([
                'ffmpeg',
                '-y',
                '-ss', '1',
                '-i', $absolute,
                '-frames:v', '1',
                '-q:v', '3',
                $thumbAbsolute,
            ]);
            $process->setTimeout(60);
            $process->run();

            if ($process->isSuccessful() && is_file($thumbAbsolute)) {
                return $thumbRelative;
            }
        } catch (ProcessFailedException $e) {
            // ffmpeg missing/failed — no thumbnail.
        } catch (\Throwable $e) {
            // ffmpeg binary not found or other failure — degrade gracefully.
        }

        return null;
    }
}
