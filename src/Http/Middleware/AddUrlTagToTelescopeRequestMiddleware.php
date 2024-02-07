<?php

namespace Wame\LaravelTelescope\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;

class AddUrlTagToTelescopeRequestMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $status = $response->status();
        $method = $request->getMethod();
        $url = $request->getBaseUrl();

        Telescope::tag("api:{$method} {$url}");
        Telescope::tag("method:{$method}");
        Telescope::tag("status:{$status}");
        Telescope::tag("url:{$url}");

        return $response;
    }
}
