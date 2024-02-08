<?php

namespace Wame\LaravelTelescope\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;

class AddUserTagToTelescopeRequestMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $userId = auth()->check() ? auth()->id() : 'guest';
        $userEmail = auth()->check() ? auth()->user()->email : 'guest@example.com';

        $tags = [
            "user_id:{$userId}",
            "user_email:{$userEmail}",
        ];

        Telescope::tag(fn () => $tags);

        return $next($request);
    }
}
