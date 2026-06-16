<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaylistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'branch_id' => $this->branch_id,
            'status' => $this->status,
            'version' => $this->version,
            'items_count' => $this->whenCounted('items'),
            'items' => PlaylistItemResource::collection($this->whenLoaded('items')),
            'assignments' => $this->whenLoaded('assignments'),
            'created_at' => $this->created_at,
        ];
    }
}
