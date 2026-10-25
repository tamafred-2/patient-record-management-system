<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScrollQueueResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->isMethod('GET') && $request->hasHeader('X-Scroll-Queue')) {
            if ($response->isSuccessful() && $request->attributes->has('scroll_queue_payload')) {
                return response()->json($request->attributes->get('scroll_queue_payload'))->header('Cache-Control', 'no-store, private');
            }
            if ($response->isSuccessful() && ! str_contains($response->headers->get('Content-Type', ''), 'application/json')) {
                return response()->json(['message' => 'This list changed. Reload the page.'], 409)->header('Cache-Control', 'no-store, private');
            }
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
