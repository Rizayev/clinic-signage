<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $guarded = [];

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }
}
