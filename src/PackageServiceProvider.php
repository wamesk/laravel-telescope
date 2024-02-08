<?php

namespace Wame\LaravelTelescope;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\Storage\DatabaseEntriesRepository;
use Laravel\Telescope\Storage\EntryModel;
use Laravel\Telescope\Storage\EntryQueryOptions;
use Laravel\Telescope\Telescope;
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

        if (env('TELESCOPE_SEARCH', true) === true) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
    }
}
