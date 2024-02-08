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
        $url = $request->getRequestUri();

        $tags = [
            "api:{$method} {$url}",
            "method:{$method}",
            "status:{$status}",
            "url:{$url}",
        ];

        Telescope::tag(fn () => $tags);

        return $response;
    }
}
