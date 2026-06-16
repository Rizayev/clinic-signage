<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmergencyMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'text' => $this->text,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'duration_seconds' => $this->duration_seconds,
            'background_color' => $this->background_color,
            'text_color' => $this->text_color,
            'started_at' => $this->started_at,
            'ends_at' => $this->ends_at,
            'scheduled_start' => $this->scheduled_start?->format('Y-m-d\TH:i'), // for <input datetime-local>
            'scheduled_end' => $this->scheduled_end?->format('Y-m-d\TH:i'),
            'display_style' => $this->display_style,
            'position' => $this->position,
            'font_size' => $this->font_size,
            'blink' => $this->blink,
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
