<?php

declare(strict_types = 1);

use Illuminate\Support\Facades\Route;

Route::post('/telescope/telescope-api/requests', [\Wame\LaravelTelescope\Http\Controllers\RequestsController::class, 'index']);
