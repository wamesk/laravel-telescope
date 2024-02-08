<?php

namespace Wame\LaravelTelescope\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;

class AddCodeTagToTelescopeRequestMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $code = $response->original['code'] ?? null;

        if (!is_null($code)) {
            Telescope::tag(fn () => ["code:{$code}"]);
        }

        return $next($request);
    }
}
