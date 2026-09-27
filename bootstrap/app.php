<?php

use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsurePasswordCurrent;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnsureActiveAccount::class, EnsurePasswordCurrent::class]);
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() !== 419 || $request->expectsJson()
                || ! $request->routeIs('login.store', 'logout')) {
                return null;
            }

            // Reject the stale submission; never replay credentials or bypass CSRF.
            $signedIn = $request->user() !== null;
            $destination = $signedIn
                ? ($request->user()->must_change_password ? 'password.edit' : 'dashboard')
                : 'login';
            $message = $signedIn
                ? 'This form expired because your session changed. You are still signed in. To log out, use the account menu again.'
                : 'This sign-in or logout form expired. Please sign in again using this refreshed form.';

            return redirect()->route($destination, [], 303)
                ->with('auth_notice', $message)
                ->withHeaders(['Cache-Control' => 'no-store, private']);
        });
    })->create();
