<?php

namespace Wame\LaravelTelescope;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Wame\LaravelTelescope\Http\Middleware;

class PackageServiceProvider extends ServiceProvider
{
    public function boot(Kernel $kernel)
    {
        if (env('TELESCOPE_TAG_URL', true) === true) {
            $kernel->pushMiddleware(Middleware\AddUrlTagToTelescopeRequestMiddleware::class);
        }

        if (env('TELESCOPE_TAG_USER', true) === true) {
            $kernel->pushMiddleware(Middleware\AddUserTagToTelescopeRequestMiddleware::class);
        }

        if (env('TELESCOPE_TAG_DATE', true) === true) {
            $kernel->pushMiddleware(Middleware\AddDateTagToTelescopeRequestMiddleware::class);
        }

        if (env('TELESCOPE_TAG_CODE', true) === true) {
            $kernel->pushMiddleware(Middleware\AddCodeTagToTelescopeRequestMiddleware::class);
        }
    }
}
