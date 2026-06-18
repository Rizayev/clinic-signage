<?php

namespace App\Services;

use App\Models\Device;
use App\Models\EmergencyMessage;
use App\Models\Playlist;
use App\Models\PlaylistAssignment;
use App\Models\Ticker;
use Carbon\Carbon;

class PlaylistResolver
{
    /**
     * Resolve the active playlist for a device following the contract priority:
     * (1) device assignment, (2) zone assignment, (3) branch assignment,
     * (4) target_type=all, then fall back to device.current_playlist_id.
     * Highest `priority` within the highest non-empty tier wins.
     */
    public function resolveForDevice(Device $device): ?Playlist
    {
        $tiers = [
            ['target_type' => 'device', 'target_id' => $device->id],
            ['target_type' => 'zone', 'target_id' => $device->zone_id],
            ['target_type' => 'branch', 'target_id' => $device->branch_id],
            ['target_type' => 'all', 'target_id' => null],
        ];

        foreach ($tiers as $tier) {
            if ($tier['target_type'] !== 'all' && empty($tier['target_id'])) {
                continue;
            }

            $assignment = PlaylistAssignment::query()
                ->where('is_active', true)
                ->where('target_type', $tier['target_type'])
                ->when(
                    $tier['target_type'] === 'all',
                    fn ($q) => $q->whereNull('target_id'),
                    fn ($q) => $q->where('target_id', $tier['target_id'])
                )
                ->whereHas('playlist', fn ($q) => $q->where('status', 'active'))
                ->orderByDesc('priority')
                ->orderByDesc('id')
                ->first();

            if ($assignment) {
                return $this->loadPlaylist($assignment->playlist_id);
            }
        }

        if ($device->current_playlist_id) {
            $playlist = Playlist::where('id', $device->current_playlist_id)
                ->where('status', 'active')
                ->first();

            if ($playlist) {
                return $this->loadPlaylist($playlist->id);
            }
        }

        return null;
    }

    /**
     * Load a playlist with its active items (ordered by sort_order) and their media.
     */
    protected function loadPlaylist(int $playlistId): ?Playlist
    {
        return Playlist::with([
            'items' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order');
            },
            'items.media',
        ])->find($playlistId);
    }

    /**
     * Resolve the active ticker for a device (most specific target wins),
     * respecting any date/time window if set.
     */
    public function resolveTicker(Device $device): ?Ticker
    {
        return $this->resolveTickers($device)->first();
    }

    /**
     * All active tickers that target this device and are within their
     * date/time window — ordered most-specific first, then by priority/id.
     * The player rotates through them. Recurring (interval_minutes) visibility
     * is computed client-side.
     */
    public function resolveTickers(Device $device): \Illuminate\Support\Collection
    {
        $now = Carbon::now();

        $candidates = Ticker::query()
            ->where('is_active', true)
            ->where(function ($q) use ($device) {
                $q->where(function ($q) use ($device) {
                    $q->where('target_type', 'device')->where('target_id', $device->id);
                });
                if ($device->zone_id) {
                    $q->orWhere(function ($q) use ($device) {
                        $q->where('target_type', 'zone')->where('target_id', $device->zone_id);
                    });
                }
                if ($device->branch_id) {
                    $q->orWhere(function ($q) use ($device) {
                        $q->where('target_type', 'branch')->where('target_id', $device->branch_id);
                    });
                }
                $q->orWhere(function ($q) {
                    $q->where('target_type', 'all');
                });
            })
            ->get();

        $specificity = ['device' => 4, 'zone' => 3, 'branch' => 2, 'all' => 1];

        return $candidates
            ->filter(fn (Ticker $t) => $this->withinWindow($t, $now))
            ->sortByDesc(fn (Ticker $t) => ($specificity[$t->target_type] ?? 0) * 1_000_000 + $t->id)
            ->values();
    }

    /**
     * Resolve the active emergency message for a device: is_active, not ended,
     * matching target (most specific wins).
     */
    public function resolveEmergency(Device $device): ?EmergencyMessage
    {
        $now = Carbon::now();

        $candidates = EmergencyMessage::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $now);
            })
            ->where(function ($q) use ($now) {
                // scheduled (future) start: not visible until it arrives
                $q->whereNull('scheduled_start')->orWhere('scheduled_start', '<=', $now);
            })
            ->where(function ($q) use ($device) {
                $q->where(function ($q) use ($device) {
                    $q->where('target_type', 'device')->where('target_id', $device->id);
                });
                if ($device->zone_id) {
                    $q->orWhere(function ($q) use ($device) {
                        $q->where('target_type', 'zone')->where('target_id', $device->zone_id);
                    });
                }
                if ($device->branch_id) {
                    $q->orWhere(function ($q) use ($device) {
                        $q->where('target_type', 'branch')->where('target_id', $device->branch_id);
                    });
                }
                $q->orWhere(function ($q) {
                    $q->where('target_type', 'all');
                });
            })
            ->get();

        $specificity = ['device' => 4, 'zone' => 3, 'branch' => 2, 'all' => 1];

        $best = null;
        $bestRank = 0;

        foreach ($candidates as $message) {
            $rank = $specificity[$message->target_type] ?? 0;
            if ($rank > $bestRank) {
                $bestRank = $rank;
                $best = $message;
            }
        }

        return $best;
    }

    /**
     * Check that "now" falls within the optional date/time window of a ticker.
     */
    protected function withinWindow(Ticker $ticker, Carbon $now): bool
    {
        if ($ticker->start_date && $now->lt(Carbon::parse($ticker->start_date)->startOfDay())) {
            return false;
        }

        if ($ticker->end_date && $now->gt(Carbon::parse($ticker->end_date)->endOfDay())) {
            return false;
        }

        $current = $now->format('H:i:s');

        if ($ticker->start_time) {
            $start = Carbon::parse($ticker->start_time)->format('H:i:s');
            if ($current < $start) {
                return false;
            }
        }

        if ($ticker->end_time) {
            $end = Carbon::parse($ticker->end_time)->format('H:i:s');
            if ($current > $end) {
                return false;
            }
        }

        // One-shot "show for N minutes from activation". Skipped for recurring
        // tickers (interval_minutes) — their cyclic window is handled client-side.
        if (! $ticker->interval_minutes && $ticker->duration_minutes && $ticker->started_at
            && $now->gt(Carbon::parse($ticker->started_at)->addMinutes($ticker->duration_minutes))) {
            return false;
        }

        return true;
    }
}
