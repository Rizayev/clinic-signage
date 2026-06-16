<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaylistAssignment extends Model
{
    use BroadcastsContentChanges;

    protected $guarded = [];

    protected $casts = [
        'target_id' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }
}
