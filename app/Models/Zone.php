<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    protected $guarded = [];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
