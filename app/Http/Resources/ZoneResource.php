<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'name' => $this->name,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'devices_count' => $this->whenCounted('devices'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
