<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\EmergencyMessage;
use App\Services\PlaylistResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PlayerController extends Controller
{
    public function __construct(protected PlaylistResolver $resolver)
    {
    }

    /**
     * Public. Register a device using its pairing code and issue an api_token.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pairing_code' => ['required', 'string'],
        ]);

        $device = Device::where('pairing_code', strtoupper($validated['pairing_code']))->first();

        if (! $device) {
            return response()->json(['message' => 'Неверный код привязки'], 404);
        }

        $device->api_token = bin2hex(random_bytes(32));
        $device->platform = $request->input('platform', $device->platform);
        $device->app_version = $request->input('app_version', $device->app_version);
        $device->android_id = $request->input('android_id', $device->android_id);
        $device->resolution = $request->input('screen_resolution', $request->input('resolution', $device->resolution));
        $device->status = 'online';
        $device->last_seen_at = now();
        $device->save();

        return response()->json([
            'success' => true,
            'device_id' => $device->id,
            'token' => $device->api_token,
            'name' => $device->name,
        ]);
    }

    /**
     * Public. Server wall-clock in unix milliseconds — used by players to
     * sync their clock (offset = server - local, round-trip compensated) so
     * playback position is computed from a shared timeline.
     */
    public function time(): JsonResponse
    {
        return response()->json(['now' => (int) round(microtime(true) * 1000)]);
    }

    /**
     * auth.device. Heartbeat — refresh presence and accept lightweight state.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $device = $request->attributes->get('device');

        $device->last_seen_at = now();
        $device->status = $request->input('status', 'online');
        $device->ip_address = $request->ip();

        if ($request->has('free_storage')) {
            $device->free_storage = $request->input('free_storage');
        }

        if ($request->has('current_playlist_id')) {
            $device->current_playlist_id = $request->input('current_playlist_id');
        }

        $device->save();

        $playlist = $this->resolver->resolveForDevice($device);

        return response()->json([
            'ok' => true,
            'config_version' => $playlist?->version,
        ]);
    }

    /**
     * auth.device. Return the resolved playlist + ticker + emergency for this device.
     */
    public function config(Request $request): JsonResponse
    {
        EmergencyMessage::expireDue();

        $device = $request->attributes->get('device');

        $playlist = $this->resolver->resolveForDevice($device);
        $tickers = $this->resolver->resolveTickers($device);
        $emergency = $this->resolver->resolveEmergency($device);

        return response()->json([
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'orientation' => $device->screen_orientation,
                'audio' => (bool) $device->audio_enabled,
            ],
            'playlist' => $this->formatPlaylist($playlist),
            // Back-compat single ticker (best) + full rotation list.
            'ticker' => $this->formatTicker($tickers->first()),
            'tickers' => $tickers->map(fn ($t) => $this->formatTicker($t))->all(),
            'emergency' => $this->formatEmergency($emergency),
        ]);
    }

    /**
     * auth.device. Insert a device log entry.
     */
    public function log(Request $request): JsonResponse
    {
        $device = $request->attributes->get('device');

        $validated = $request->validate([
            'event' => ['required', 'string'],
        ]);

        DeviceLog::create([
            'device_id' => $device->id,
            'level' => $request->input('level', 'info'),
            'event' => $validated['event'],
            'message' => $request->input('message'),
            'payload' => $request->input('payload'),
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * auth.device. Lightweight revision signature. The player polls this
     * frequently (cheap) and only re-downloads the full /config when the
     * revision changes — so activating/deactivating an emergency, swapping
     * a playlist or editing a ticker is picked up within a few seconds.
     */
    public function state(Request $request): JsonResponse
    {
        EmergencyMessage::expireDue();

        $device = $request->attributes->get('device');

        $playlist = $this->resolver->resolveForDevice($device);
        $tickers = $this->resolver->resolveTickers($device);
        $emergency = $this->resolver->resolveEmergency($device);

        $revision = implode('|', [
            'p:'.($playlist ? $playlist->id.'.'.$playlist->version : '0'),
            'e:'.($emergency
                ? $emergency->id.'.'.(optional($emergency->updated_at)->timestamp ?? '1')
                    .'.'.(optional($emergency->ends_at)->timestamp ?? '0')
                    .'.'.(optional($emergency->scheduled_start)->timestamp ?? '0')
                : '0'),
            't:'.($tickers->isNotEmpty()
                ? $tickers->map(fn ($t) => $t->id.'.'.(optional($t->updated_at)->timestamp ?? '1'))->implode(',')
                : '0'),
            'a:'.((int) $device->audio_enabled),
        ]);

        return response()->json(['revision' => $revision]);
    }

    protected function formatPlaylist($playlist): ?array
    {
        if (! $playlist) {
            return null;
        }

        $items = $playlist->items->map(function ($item) {
            $media = $item->media;

            return [
                'id' => $item->id,
                'media_id' => $item->media_id,
                'type' => $media?->type,
                'url' => $media && $media->file_path
                    ? Storage::disk('public')->url($media->file_path)
                    : null,
                'duration' => $item->duration_seconds ?? $media?->duration ?? 10,
                'transition' => $item->transition_effect,
                'checksum' => $media?->checksum,
            ];
        })->values()->all();

        return [
            'id' => $playlist->id,
            'version' => $playlist->version,
            'items' => $items,
        ];
    }

    protected function formatTicker($ticker): array
    {
        if (! $ticker) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'id' => $ticker->id,
            'text' => $ticker->text,
            'position' => $ticker->position,
            'speed' => $ticker->speed,
            'font_size' => $ticker->font_size,
            'text_color' => $ticker->text_color,
            'background_color' => $ticker->background_color,
            'opacity' => (float) $ticker->opacity,
            'repeat_count' => $ticker->repeat_count ?: null, // null/0 = infinite
            'duration_minutes' => $ticker->duration_minutes ?: null,
            'interval_minutes' => $ticker->interval_minutes ?: null, // recurring window
            'started_at_ms' => $ticker->started_at?->valueOf(),
        ];
    }

    protected function formatEmergency($emergency): array
    {
        if (! $emergency) {
            return [
                'active' => false,
                'text' => null,
                'background_color' => '#b00020',
                'text_color' => '#ffffff',
            ];
        }

        return [
            'active' => true,
            'title' => $emergency->title,
            'text' => $emergency->text,
            'background_color' => $emergency->background_color,
            'text_color' => $emergency->text_color,
            'display_style' => $emergency->display_style ?? 'fullscreen',
            'position' => $emergency->position ?? 'bottom',
            'font_size' => (int) ($emergency->font_size ?? 48),
            'blink' => (bool) $emergency->blink,
            'scheduled_start_ms' => $emergency->scheduled_start?->valueOf(),
            'ends_at_ms' => $emergency->ends_at?->valueOf(),
        ];
    }
}
