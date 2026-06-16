<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyMessage extends Model
{
    use BroadcastsContentChanges;

    protected $guarded = [];

    protected $casts = [
        'target_id' => 'integer',
        'duration_seconds' => 'integer',
        'started_at' => 'datetime',
        'ends_at' => 'datetime',
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'font_size' => 'integer',
        'blink' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Turn off any active emergency whose end has passed. Called from HTTP
     * requests (player poll / admin list) so the per-row update() fires the
     * BroadcastsContentChanges trait → players are notified instantly over the
     * websocket. Self-limiting: already-inactive rows no longer match.
     */
    public static function expireDue(): void
    {
        static::query()
            ->where('is_active', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->get()
            ->each(fn (self $m) => $m->update(['is_active' => false]));
    }
}

