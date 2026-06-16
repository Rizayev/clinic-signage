<?php

use Illuminate\Support\Facades\Route;

// Browser player (digital signage screen)
Route::view('/player', 'player');

// Admin SPA — catch-all (excludes api, health, storage, player prefixes)
Route::view('/', 'app');
Route::get('/{any}', fn () => view('app'))
    ->where('any', '^(?!api|up|storage|player).*$');
