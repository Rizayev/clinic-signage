<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\EmergencyMessage;
use App\Models\Media;
use App\Models\Playlist;
use App\Models\Ticker;
use App\Models\Zone;

class DashboardController extends Controller
{
    public function index()
    {
        $recentLogs = DeviceLog::with('device:id,name')
            ->latest('created_at')
            ->take(10)
            ->get()
            ->map(function (DeviceLog $log) {
                return [
                    'id' => $log->id,
                    'device_id' => $log->device_id,
                    'device_name' => $log->device?->name,
                    'level' => $log->level,
                    'event' => $log->event,
                    'message' => $log->message,
                    'created_at' => $log->created_at,
                ];
            });

        return response()->json([
            'devices_total' => Device::count(),
            'devices_online' => Device::where('status', 'online')->count(),
            'devices_offline' => Device::where('status', 'offline')->count(),
            'devices_error' => Device::where('status', 'error')->count(),
            'playlists_active' => Playlist::where('status', 'active')->count(),
            'tickers_active' => Ticker::where('is_active', true)->count(),
            'media_total' => Media::count(),
            'branches_total' => Branch::count(),
            'zones_total' => Zone::count(),
            'recent_logs' => $recentLogs,
            'emergency_active' => EmergencyMessage::where('is_active', true)->exists(),
        ]);
    }
}
