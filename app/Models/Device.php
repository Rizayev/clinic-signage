<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'last_seen_at' => 'datetime',
        'free_storage' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function currentPlaylist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class, 'current_playlist_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DeviceLog::class);
    }
}
