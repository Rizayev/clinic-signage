<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TickerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'title' => $this->title,
            'text' => $this->text,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'position' => $this->position,
            'speed' => $this->speed,
            'font_size' => $this->font_size,
            'text_color' => $this->text_color,
            'background_color' => $this->background_color,
            'opacity' => $this->opacity,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'repeat_count' => $this->repeat_count,
            'duration_minutes' => $this->duration_minutes,
            'started_at' => $this->started_at,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
