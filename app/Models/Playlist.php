<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Playlist extends Model
{
    use BroadcastsContentChanges;

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class)->orderBy('sort_order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PlaylistAssignment::class);
    }
}
