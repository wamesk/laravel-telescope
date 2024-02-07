<?php

namespace Wame\LaravelTelescope;

use Illuminate\Support\ServiceProvider;
use Wame\LaravelTelescope\Http\Middleware;

class PackageServiceProvider extends ServiceProvider
{
    public function register()
    {
        if (env('TELESCOPE_TAG_URL', true) === true) {
            $this->app->make(Middleware\AddUrlTagToTelescopeRequestMiddleware::class);
        }

        if (env('TELESCOPE_TAG_USER', true) === true) {
            $this->app->make(Middleware\AddUserTagToTelescopeRequestMiddleware::class);
        }

        if (env('TELESCOPE_TAG_DATE', true) === true) {
            $this->app->make(Middleware\AddDateTagToTelescopeRequestMiddleware::class);
        }

        if (env('TELESCOPE_TAG_CODE', true) === true) {
            $this->app->make(Middleware\AddCodeTagToTelescopeRequestMiddleware::class);
        }
    }
}
