<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function __construct(protected MediaService $mediaService)
    {
    }

    public function index(Request $request)
    {
        $query = Media::query()->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('q')) {
            $query->where('title', 'like', '%' . $request->input('q') . '%');
        }

        return MediaResource::collection($query->paginate(24));
    }

    public function show(Media $media)
    {
        return new MediaResource($media);
    }

    public function store(Request $request)
    {
        $type = $request->input('type');

        if ($type === 'text' || $type === 'html') {
            $data = $request->validate([
                'title' => ['nullable', 'string', 'max:255'],
                'category' => ['nullable', 'string', 'max:255'],
                'body' => ['required', 'string'],
            ]);

            $path = 'media/' . \Illuminate\Support\Str::uuid()->toString() . '.html';
            Storage::disk('public')->put($path, $data['body']);

            $media = new Media();
            $media->title = $data['title'] ?? 'Untitled';
            $media->category = $data['category'] ?? null;
            $media->type = $type === 'text' ? 'text' : 'html';
            $media->mime_type = 'text/html';
            $media->file_path = $path;
            $media->size = strlen($data['body']);
            $media->checksum = hash('sha256', $data['body']);
            $media->status = 'active';
            $media->created_by = Auth::id();
            $media->save();

            return (new MediaResource($media))
                ->response()
                ->setStatusCode(201);
        }

        $data = $request->validate([
            'file' => ['required', 'file', 'max:2097152'],
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
        ]);

        $media = $this->mediaService->handleUpload($request->file('file'), [
            'title' => $data['title'] ?? null,
            'category' => $data['category'] ?? null,
        ]);

        return (new MediaResource($media))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Media $media)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive,processing'],
            'duration' => ['nullable', 'integer', 'min:0'],
        ]);

        $media->fill(array_filter($data, fn ($value) => $value !== null));
        $media->save();

        return new MediaResource($media);
    }

    public function replace(Request $request, Media $media)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:2097152'],
        ]);

        $media = $this->mediaService->replaceFile($media, $request->file('file'));

        return new MediaResource($media);
    }

    public function destroy(Media $media)
    {
        $disk = Storage::disk('public');

        if ($media->file_path && $disk->exists($media->file_path)) {
            $disk->delete($media->file_path);
        }

        if ($media->thumbnail_path && $disk->exists($media->thumbnail_path)) {
            $disk->delete($media->thumbnail_path);
        }

        $media->delete();

        return response()->json(['message' => 'Удалено']);
    }
}
