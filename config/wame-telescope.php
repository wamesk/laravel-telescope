<?php

declare(strict_types = 1);

return [
    'search' => env('TELESCOPE_SEARCH', true),

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
