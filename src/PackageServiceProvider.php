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
            $hours = env('TELESCOPE_PRUNE_HOURS', 48);
            if (false !== $hours) {
                $this->app->booted(function (): void {
                    $schedule = $this->app->make(Schedule::class);
                    $schedule->command('telescope:prune --hours=' . env('TELESCOPE_PRUNE_HOURS', 48))->daily();
                });
            }
        }
    }
}
