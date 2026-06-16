<?php

namespace App\Models\Concerns;

use App\Events\ContentChanged;

/**
 * Models using this trait broadcast a ContentChanged signal whenever they are
 * saved or deleted via an HTTP request (admin action). Console contexts
 * (seeding, migrations, tinker, queue) are skipped so demo seeding doesn't spam
 * Reverb, and any broadcast failure (e.g. Reverb not running) is swallowed so it
 * never breaks the write itself — players still fall back to polling.
 */
trait BroadcastsContentChanges
{
    public static function bootBroadcastsContentChanges(): void
    {
        $fire = function ($model): void {
            if (app()->runningInConsole()) {
                return;
            }
            try {
                ContentChanged::dispatch(class_basename($model));
            } catch (\Throwable $e) {
                // Reverb unavailable — players keep working via the polling fallback.
            }
        };

        static::saved($fire);
        static::deleted($fire);
    }
}
