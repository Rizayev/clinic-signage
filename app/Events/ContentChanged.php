<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast on a public channel that every player subscribes to. The payload
 * carries no sensitive data — it is only a "something changed, re-check"
 * signal. Players react by re-fetching /player/state (revision compare) and
 * pulling a fresh /config only when the revision actually changed.
 */
class ContentChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public string $type = 'content')
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('signage');
    }

    public function broadcastAs(): string
    {
        return 'content.changed';
    }

    public function broadcastWith(): array
    {
        return ['type' => $this->type];
    }
}
