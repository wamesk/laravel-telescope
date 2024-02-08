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

        $method = $request->getMethod();
        $url = $request->getRequestUri();

        $tags = [
            "api:{$method} {$url}",
            "method:{$method}",
            "status:{$response->status()}",
            "full_url:{$url}",
            "path:{$request->path()}",
        ];

        Telescope::tag(fn () => $tags);

        return $response;
    }
}
