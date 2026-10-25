<?php

use App\Exceptions\ExportUnavailable;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsurePasswordCurrent;
use App\Http\Middleware\RecordUserActivity;
use App\Http\Middleware\ScrollQueueResponse;
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
        $middleware->web(append: [EnsureActiveAccount::class, EnsurePasswordCurrent::class, RecordUserActivity::class, ScrollQueueResponse::class]);
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([ExportUnavailable::class]);
        $exceptions->render(function (ExportUnavailable $exception, Request $request) {
            $response = $request->expectsJson()
                ? response()->json(['message' => $exception->getMessage()], $exception->getStatusCode())
                : response()->view('errors.export-unavailable', ['message' => $exception->getMessage()], $exception->getStatusCode());

            return $response->withHeaders($exception->getHeaders())->header('Cache-Control', 'no-store, private');
        });
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
