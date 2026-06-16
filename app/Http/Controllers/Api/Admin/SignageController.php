<?php

namespace App\Http\Controllers\Api\Admin;

use App\Events\ResyncCommand;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SignageController extends Controller
{
    /**
     * Broadcast a rendezvous resync: every player converges on the schedule
     * position at `at` (now + lead), masked by a crossfade. `at` uses the same
     * server-ms clock as PlayerController::time(), so it lands in the players'
     * synced-clock domain. The lead gives screens time to preload+seek a hidden
     * buffer slot before the synchronized swap.
     */
    public function resync(): JsonResponse
    {
        $at = (int) round(microtime(true) * 1000) + 12000; // +12s convergence window

        ResyncCommand::dispatch($at);

        return response()->json(['ok' => true, 'at' => $at]);
    }
}
