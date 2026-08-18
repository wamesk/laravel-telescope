<?php

namespace Wame\LaravelTelescope;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Wame\LaravelTelescope\Http\Middleware\AddTagsToTelescopeRequestMiddleware;

class PackageServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/wame-telescope.php', 'wame-telescope');
    }

    public function boot(Kernel $kernel)
    {
        $kernel->pushMiddleware(AddTagsToTelescopeRequestMiddleware::class);

        if (config('wame-telescope.search')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/wame-telescope.php' => config_path('wame-telescope.php'),
            ], 'wame-telescope-config');

            $this->schedulePrune();
        }
    }

    /**
     * Schedule the Telescope prune.
     *
     * The retention window is read from the configuration and never from env()
     * directly: once "php artisan config:cache" has run, Laravel skips loading the
     * .env file entirely, so a runtime env() call silently returns its default
     * instead of the configured value.
     */
    protected function schedulePrune(): void
    {
        $hours = config('wame-telescope.prune_hours');

        if (false === $hours || null === $hours) {
            return;
        }

        $this->app->booted(function () use ($hours): void {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('telescope:prune --hours=' . $hours)->daily();
        });
    }
}
