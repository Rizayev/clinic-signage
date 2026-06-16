<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A rendezvous command broadcast to every player: "at server-wall-clock `at`
 * (unix ms), re-align playback together". Each player independently computes
 * what will be correct at `at`, preloads it into a hidden buffer slot, then
 * crossfades exactly at `at` — so all screens converge in lockstep, masked by
 * the fade (no visible frame jump). `at` is raw server ms; players compare it
 * against their synced clock (syncedNow ≈ server ms) directly.
 */
class ResyncCommand implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $at)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('signage');
    }

    public function broadcastAs(): string
    {
        return 'resync';
    }

    public function broadcastWith(): array
    {
        return ['at' => $this->at];
    }
}
