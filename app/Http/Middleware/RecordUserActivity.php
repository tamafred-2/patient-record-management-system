<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        // Runs after active-account and password-version checks. At most one write per minute.
        if (($user = $request->user()) && ! $request->routeIs('logout')) {
            User::whereKey($user->id)->where(function ($query) {
                $query->whereNull('last_active_at')->orWhere('last_active_at', '<=', now()->subMinute());
            })->toBase()->update(['last_active_at' => now()]);
        }

        return $next($request);
    }
}
