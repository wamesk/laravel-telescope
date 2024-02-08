<?php

namespace Wame\LaravelTelescope\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;

class AddDateTagToTelescopeRequestMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $tags = [
            "date:" . date('Y-m-d'),
            "date_time:" . date('Y-m-d H:i:s'),
            "time:" . date('H:i'),
        ];

        Telescope::tag(fn () => $tags);

        return $next($request);
    }
}
