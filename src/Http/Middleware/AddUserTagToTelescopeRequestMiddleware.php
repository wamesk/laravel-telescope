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

        Telescope::tag("user_id:{$userId}");
        Telescope::tag("user_email:{$userEmail}");

        return $next($request);
    }
}
