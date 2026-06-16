<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticker extends Model
{
    use BroadcastsContentChanges;

    protected $guarded = [];

    protected $casts = [
        'target_id' => 'integer',
        'speed' => 'integer',
        'font_size' => 'integer',
        'opacity' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'repeat_count' => 'integer',
        'duration_minutes' => 'integer',
        'started_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
