<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'timezone' => $this->timezone,
            'status' => $this->status,
            'zones_count' => $this->whenCounted('zones'),
            'devices_count' => $this->whenCounted('devices'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
