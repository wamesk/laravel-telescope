<?php

declare(strict_types = 1);

use Illuminate\Support\Facades\Route;
use Laravel\Telescope\Http\Middleware\Authorize;
use Wame\LaravelTelescope\Http\Controllers\RequestsController;

/*
 * The request search behind the same path, domain and middleware as Telescope's own API,
 * with Telescope's Authorize middleware always applied: without it the route answered
 * anonymous requests with the stored request entries, e.g. while Telescope's own
 * routes are not registered because Telescope is disabled.
 */
Route::group([
    'domain' => config('telescope.domain'),
    'prefix' => config('telescope.path', 'telescope'),
    'middleware' => array_values(array_unique([
        ...(array) config('telescope.middleware', ['web']),
        Authorize::class,
    ])),
], function (): void {
    Route::post('telescope-api/requests', [RequestsController::class, 'index']);
});
