<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Media extends Model
{
    protected $guarded = [];

    protected $table = 'media';

    protected $casts = [
        'size' => 'integer',
        'duration' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function playlistItems(): HasMany
    {
        return $this->hasMany(PlaylistItem::class);
    }
}
