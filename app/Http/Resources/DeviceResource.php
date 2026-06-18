<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'zone_id' => $this->zone_id,
            'name' => $this->name,
            'device_code' => $this->device_code,
            'pairing_code' => $this->pairing_code,
            'platform' => $this->platform,
            'device_type' => $this->device_type,
            'ip_address' => $this->ip_address,
            'mac_address' => $this->mac_address,
            'android_id' => $this->android_id,
            'screen_orientation' => $this->screen_orientation,
            'audio_enabled' => (bool) $this->audio_enabled,
            'resolution' => $this->resolution,
            'status' => $this->status,
            'last_seen_at' => $this->last_seen_at,
            'last_seen_human' => $this->last_seen_at
                ? $this->last_seen_at->diffForHumans()
                : null,
            'app_version' => $this->app_version,
            'current_playlist_id' => $this->current_playlist_id,
            'free_storage' => $this->free_storage,
            'settings' => $this->settings,
            'zone' => $this->whenLoaded('zone', fn () => [
                'id' => $this->zone?->id,
                'name' => $this->zone?->name,
            ]),
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch?->id,
                'name' => $this->branch?->name,
            ]),
            'current_playlist' => $this->whenLoaded('currentPlaylist', fn () => $this->currentPlaylist ? [
                'id' => $this->currentPlaylist->id,
                'name' => $this->currentPlaylist->name,
            ] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
