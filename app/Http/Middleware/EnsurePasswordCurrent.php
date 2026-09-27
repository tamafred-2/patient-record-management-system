<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $user->refresh();
            // A valid remember cookie starts a new session after normal expiry.
            if (Auth::viaRemember() && ! $request->session()->has('auth_password_version')) {
                $request->session()->put('auth_password_version', $user->password_version);
            }
            if ((int) $request->session()->get('auth_password_version', 0) !== $user->password_version) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => 'Your password changed. Please sign in again.']);
            }
            if ($user->must_change_password && ! $request->routeIs('password.edit', 'password.update', 'logout')) {
                return redirect()->route('password.edit');
            }
        }

        return $next($request);
    }
}
