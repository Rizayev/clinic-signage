<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaylistItem extends Model
{
    use BroadcastsContentChanges;

    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'integer',
        'duration_seconds' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'days_of_week' => 'array',
        'is_active' => 'boolean',
    ];

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
