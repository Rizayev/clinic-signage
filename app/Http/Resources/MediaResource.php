<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'category' => $this->category,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'duration' => $this->duration,
            'width' => $this->width,
            'height' => $this->height,
            'status' => $this->status,
            'file_url' => $this->file_path
                ? Storage::disk('public')->url($this->file_path)
                : null,
            'thumbnail_url' => $this->thumbnail_path
                ? Storage::disk('public')->url($this->thumbnail_path)
                : null,
            'created_at' => $this->created_at,
        ];
    }
}
