<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\PlaylistAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $query = Device::query()->with(['zone', 'branch', 'currentPlaylist']);

        if ($branchId = $request->query('branch_id')) {
            $query->where('branch_id', $branchId);
        }

        if ($zoneId = $request->query('zone_id')) {
            $query->where('zone_id', $zoneId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($q = $request->query('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('device_code', 'like', "%{$q}%");
            });
        }

        return DeviceResource::collection($query->paginate(20));
    }

    public function show(Device $device)
    {
        $device->load(['zone', 'branch', 'currentPlaylist']);

        return new DeviceResource($device);
    }

    public function store(StoreDeviceRequest $request)
    {
        $data = $request->validated();
        $data['device_type'] = $data['device_type'] ?? 'android_tv';
        $data['screen_orientation'] = $data['screen_orientation'] ?? 'landscape';
        $data['status'] = 'offline';
        $data['pairing_code'] = $this->generatePairingCode();

        $device = Device::create($data);

        $device->load(['zone', 'branch', 'currentPlaylist']);

        return new DeviceResource($device);
    }

    public function update(UpdateDeviceRequest $request, Device $device)
    {
        $data = $request->validated();

        $device->update($data);

        $device->load(['zone', 'branch', 'currentPlaylist']);

        return new DeviceResource($device);
    }

    public function destroy(Device $device)
    {
        $device->delete();

        return response()->json(['success' => true]);
    }

    public function pair(Request $request)
    {
        $data = $request->validate([
            'pairing_code' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $device = Device::where('pairing_code', $data['pairing_code'])->first();

        if (! $device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        $device->name = $data['name'];

        if (array_key_exists('zone_id', $data)) {
            $device->zone_id = $data['zone_id'];
        }

        if (! empty($data['branch_id'])) {
            $device->branch_id = $data['branch_id'];
        }

        $device->save();

        $device->load(['zone', 'branch', 'currentPlaylist']);

        return new DeviceResource($device);
    }

    public function assignPlaylist(Request $request, Device $device)
    {
        $data = $request->validate([
            'playlist_id' => ['nullable', 'integer', 'exists:playlists,id'],
        ]);

        $playlistId = $data['playlist_id'] ?? null;

        $device->current_playlist_id = $playlistId;
        $device->save();

        PlaylistAssignment::where('target_type', 'device')
            ->where('target_id', $device->id)
            ->delete();

        if ($playlistId) {
            PlaylistAssignment::create([
                'playlist_id' => $playlistId,
                'target_type' => 'device',
                'target_id' => $device->id,
                'priority' => 100,
                'is_active' => true,
            ]);
        }

        $device->load(['zone', 'branch', 'currentPlaylist']);

        return new DeviceResource($device);
    }

    public function logs(Device $device)
    {
        $logs = DeviceLog::where('device_id', $device->id)
            ->latest('created_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $logs]);
    }

    protected function generatePairingCode(): string
    {
        do {
            $raw = Str::upper(Str::random(6));
            $code = substr($raw, 0, 4) . '-' . substr($raw, 4, 2);
        } while (Device::where('pairing_code', $code)->exists());

        return $code;
    }
}
