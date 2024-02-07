<?php

namespace Wame\LaravelTelescope\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;

class AddDateTagToTelescopeRequestMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        Telescope::tag("date:" . date('Y-m-d'));
        Telescope::tag("date_time:" . date('Y-m-d H:i:s'));
        Telescope::tag("time:" . date('H:i'));

        return $next($request);
    }
}
