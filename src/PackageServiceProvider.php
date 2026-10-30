<?php

namespace Wame\LaravelTelescope;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\Contracts\PrunableRepository;
use Wame\LaravelTelescope\Http\Middleware\AddTagsToTelescopeRequestMiddleware;
use Wame\LaravelTelescope\Storage\DatabaseEntriesRepository;

class PackageServiceProvider extends ServiceProvider
{
    /**
     * Time of day of the scheduled prune when the configuration does not set one.
     */
    public const DEFAULT_PRUNE_AT = '03:30';

    /**
     * Minutes after which the overlap lock of the scheduled prune expires.
     *
     * Shorter than a day, so a prune process that was killed without releasing
     * the lock never blocks the run of the following night.
     */
    public const PRUNE_LOCK_MINUTES = 720;

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/wame-telescope.php', 'wame-telescope');
    }

    public function boot(Kernel $kernel)
    {
        $kernel->pushMiddleware(AddTagsToTelescopeRequestMiddleware::class);

        $this->registerRepository();

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
     * Resolve Telescope's storage and "telescope:prune" to the package repository.
     *
     * The package repository stores exceptions without locking their whole family,
     * waits only briefly for row locks while storing and retries lock conflicts while
     * pruning. Telescope binds its own repository while registering, so overriding
     * the bindings here, in boot(), wins. Only the database driver is replaced.
     */
    protected function registerRepository(): void
    {
        if ('database' !== config('telescope.driver')) {
            return;
        }

        $this->app->singleton(EntriesRepository::class, DatabaseEntriesRepository::class);
        $this->app->singleton(PrunableRepository::class, DatabaseEntriesRepository::class);

        $this->app->when(DatabaseEntriesRepository::class)
            ->needs('$connection')
            ->give(fn () => config('telescope.storage.database.connection'));

        $this->app->when(DatabaseEntriesRepository::class)
            ->needs('$chunkSize')
            ->give(fn () => config('telescope.storage.database.chunk'));
    }

    /**
     * Schedule the Telescope prune.
     *
     * The retention window and the time of day are read from the configuration and
     * never from env() directly: once "php artisan config:cache" has run, Laravel
     * skips loading the .env file entirely, so a runtime env() call silently returns
     * its default instead of the configured value.
     *
     * The prune runs once a day outside midnight, when database backups and
     * maintenance commonly hold locks it would wait on. It runs on a single server
     * and never overlaps a previous run that is still deleting. It runs in the
     * background, so its lock retries never delay the tasks due in the same minute.
     */
    protected function schedulePrune(): void
    {
        $hours = config('wame-telescope.prune_hours');

        if (false === $hours || null === $hours) {
            return;
        }

        $at = $this->pruneAt();

        $this->app->booted(function () use ($hours, $at): void {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('telescope:prune --hours=' . $hours)
                ->dailyAt($at)
                ->onOneServer()
                ->withoutOverlapping(self::PRUNE_LOCK_MINUTES)
                ->runInBackground();
        });
    }

    /**
     * The time of day of the scheduled prune, always a valid "H:MM" / "HH:MM" time.
     *
     * An invalid value falls back to the default and logs a warning instead of being
     * handed to the scheduler: an hour such as "25:00" builds a cron expression that
     * throws while the scheduler evaluates it, which stops every scheduled task of
     * the application, and a value such as "abc" quietly turns into midnight.
     */
    protected function pruneAt(): string
    {
        $at = trim((string) config('wame-telescope.prune_at'));

        if ('' === $at) {
            return self::DEFAULT_PRUNE_AT;
        }

        if (1 !== preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $at)) {
            Log::warning('Invalid wame-telescope.prune_at, the Telescope prune runs at the default time instead.', [
                'prune_at' => $at,
                'default' => self::DEFAULT_PRUNE_AT,
            ]);

            return self::DEFAULT_PRUNE_AT;
        }

        return $at;
    }
}
