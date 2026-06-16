<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmergencyMessageRequest;
use App\Http\Resources\EmergencyMessageResource;
use App\Models\EmergencyMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EmergencyMessageController extends Controller
{
    public function index(Request $request)
    {
        EmergencyMessage::expireDue(); // flip off any whose end has passed (broadcasts)

        $messages = EmergencyMessage::query()
            ->latest()
            ->paginate(20);

        return EmergencyMessageResource::collection($messages);
    }

    public function store(StoreEmergencyMessageRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $emergencyMessage = EmergencyMessage::create($data);

        return new EmergencyMessageResource($emergencyMessage);
    }

    public function show(EmergencyMessage $emergencyMessage)
    {
        return new EmergencyMessageResource($emergencyMessage);
    }

    public function update(StoreEmergencyMessageRequest $request, EmergencyMessage $emergencyMessage)
    {
        $emergencyMessage->update($request->validated());

        return new EmergencyMessageResource($emergencyMessage);
    }

    public function destroy(EmergencyMessage $emergencyMessage)
    {
        $emergencyMessage->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function activate(EmergencyMessage $emergencyMessage)
    {
        $now = Carbon::now();
        // Effective start = scheduled future start, or now. End = explicit
        // scheduled end, else now/start + duration_seconds, else manual (null).
        $effectiveStart = $emergencyMessage->scheduled_start ?? $now;

        $emergencyMessage->is_active = true;
        $emergencyMessage->started_at = $effectiveStart;
        $emergencyMessage->ends_at = $emergencyMessage->scheduled_end
            ?? ($emergencyMessage->duration_seconds
                ? $effectiveStart->copy()->addSeconds($emergencyMessage->duration_seconds)
                : null);
        $emergencyMessage->save();

        return new EmergencyMessageResource($emergencyMessage);
    }

    public function deactivate(EmergencyMessage $emergencyMessage)
    {
        $emergencyMessage->is_active = false;
        $emergencyMessage->ends_at = Carbon::now();
        $emergencyMessage->save();

        return new EmergencyMessageResource($emergencyMessage);
    }
}
