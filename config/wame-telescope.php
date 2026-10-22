<?php

declare(strict_types = 1);

return [
    'search' => env('TELESCOPE_SEARCH', true),

    /*
     * How many hours of Telescope data the scheduled "telescope:prune" keeps.
     * Set to false to stop the package from scheduling the prune at all.
     *
     * Publish this file into the application ("config/wame-telescope.php") so the
     * value survives "php artisan config:cache" - a cached configuration means the
     * .env file is never loaded, and a package config merged at runtime would fall
     * back to the default below instead of the configured value.
     */
    'prune_hours' => env('TELESCOPE_PRUNE_HOURS', 48),

    /*
     * Time of day ("HH:MM", application timezone) when the scheduled "telescope:prune"
     * runs. Kept away from midnight on purpose: database backups and maintenance
     * commonly run then and hold locks the prune would wait on until it fails with
     * "1205 Lock wait timeout exceeded".
     */
    'prune_at' => env('TELESCOPE_PRUNE_AT', '03:30'),

    'api' => env('TELESCOPE_TAG_API', false),
    'code' => env('TELESCOPE_TAG_CODE', true),
    'date' => env('TELESCOPE_TAG_DATE', false),
    'date_time' => env('TELESCOPE_TAG_DATE_TIME', false),
    'email' => env('TELESCOPE_TAG_EMAIL', true),
    'errors' => env('TELESCOPE_TAG_ERRORS', true),
    'hour' => env('TELESCOPE_TAG_HOUR', false),
    'method' => env('TELESCOPE_TAG_METHOD', false),
    'month' => env('TELESCOPE_TAG_MONTH', false),
    'path' => env('TELESCOPE_TAG_PATH', true),
    'status' => env('TELESCOPE_TAG_STATUS', false),
    'time' => env('TELESCOPE_TAG_TIME', false),
    'url' => env('TELESCOPE_TAG_URL', false),
];
