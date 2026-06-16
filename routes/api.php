<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\BranchController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\DeviceController;
use App\Http\Controllers\Api\Admin\EmergencyMessageController;
use App\Http\Controllers\Api\Admin\MediaController;
use App\Http\Controllers\Api\Admin\PlaylistController;
use App\Http\Controllers\Api\Admin\SignageController;
use App\Http\Controllers\Api\Admin\TickerController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\ZoneController;
use App\Http\Controllers\Api\Player\PlayerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public auth
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Player API
|--------------------------------------------------------------------------
*/
Route::prefix('player')->group(function () {
    Route::post('register', [PlayerController::class, 'register']);
    Route::get('time', [PlayerController::class, 'time']);

    Route::middleware('auth.device')->group(function () {
        Route::post('heartbeat', [PlayerController::class, 'heartbeat']);
        Route::get('config', [PlayerController::class, 'config']);
        Route::get('state', [PlayerController::class, 'state']);
        Route::post('log', [PlayerController::class, 'log']);
    });
});

/*
|--------------------------------------------------------------------------
| Admin API (Sanctum token auth)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Broadcast a rendezvous resync command to all players (smooth fade-aligned).
    Route::middleware('write:branch_admin,content_manager')->post('/resync', [SignageController::class, 'resync']);

    // Infrastructure (branches, zones, devices): writes for super_admin + branch_admin.
    // GET is allowed for every authenticated role (incl. viewer) by the `write` middleware.
    Route::middleware('write:branch_admin')->group(function () {
        Route::apiResource('branches', BranchController::class);
        Route::apiResource('zones', ZoneController::class);

        Route::post('devices/pair', [DeviceController::class, 'pair']);
        Route::post('devices/{device}/assign-playlist', [DeviceController::class, 'assignPlaylist']);
        Route::get('devices/{device}/logs', [DeviceController::class, 'logs']);
        Route::apiResource('devices', DeviceController::class);
    });

    // Content (media, playlists, tickers, emergency): writes also allowed for content_manager.
    Route::middleware('write:branch_admin,content_manager')->group(function () {
        Route::post('media/{media}/replace', [MediaController::class, 'replace']);
        Route::apiResource('media', MediaController::class)->parameters(['media' => 'media']);

        Route::post('playlists/{playlist}/items', [PlaylistController::class, 'storeItem']);
        Route::put('playlists/{playlist}/items/{item}', [PlaylistController::class, 'updateItem']);
        Route::delete('playlists/{playlist}/items/{item}', [PlaylistController::class, 'destroyItem']);
        Route::post('playlists/{playlist}/reorder', [PlaylistController::class, 'reorder']);
        Route::post('playlists/{playlist}/assign', [PlaylistController::class, 'assign']);
        Route::apiResource('playlists', PlaylistController::class);

        Route::apiResource('tickers', TickerController::class);

        Route::post('emergency-messages/{emergencyMessage}/activate', [EmergencyMessageController::class, 'activate']);
        Route::post('emergency-messages/{emergencyMessage}/deactivate', [EmergencyMessageController::class, 'deactivate']);
        Route::apiResource('emergency-messages', EmergencyMessageController::class)
            ->parameters(['emergency-messages' => 'emergencyMessage']);
    });

    // User management: super_admin only (all verbs).
    Route::middleware('role:super_admin')->group(function () {
        Route::apiResource('users', UserController::class);
    });
});
