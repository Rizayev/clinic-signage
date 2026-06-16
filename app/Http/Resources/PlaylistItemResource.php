<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaylistItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'playlist_id' => $this->playlist_id,
            'media_id' => $this->media_id,
            'sort_order' => $this->sort_order,
            'duration_seconds' => $this->duration_seconds,
            'transition_effect' => $this->transition_effect,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'days_of_week' => $this->days_of_week,
            'is_active' => $this->is_active,
            'media' => MediaResource::make($this->whenLoaded('media')),
        ];
    }
}
