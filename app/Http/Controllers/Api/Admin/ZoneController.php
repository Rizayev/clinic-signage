<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreZoneRequest;
use App\Http\Requests\UpdateZoneRequest;
use App\Http\Resources\ZoneResource;
use App\Models\Zone;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function index(Request $request)
    {
        $query = Zone::query()->with('branch')->withCount('devices');

        if ($branchId = $request->query('branch_id')) {
            $query->where('branch_id', $branchId);
        }

        return ZoneResource::collection($query->paginate(20));
    }

    public function store(StoreZoneRequest $request)
    {
        $data = $request->validated();
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $zone = Zone::create($data);

        return new ZoneResource($zone->load('branch')->loadCount('devices'));
    }

    public function show(Zone $zone)
    {
        return new ZoneResource($zone->load('branch')->loadCount('devices'));
    }

    public function update(UpdateZoneRequest $request, Zone $zone)
    {
        $data = $request->validated();
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $zone->update($data);

        return new ZoneResource($zone->load('branch')->loadCount('devices'));
    }

    public function destroy(Zone $zone)
    {
        $zone->delete();

        return response()->json(['success' => true]);
    }
}
