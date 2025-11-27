<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebSocketFallback
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Add WebSocket fallback headers for organization pages
        if ($request->is('organization*') || $request->is('dashboard*')) {
            $response->headers->set('X-WebSocket-Fallback', 'enabled');
            $response->headers->set('X-Polling-Interval', '30000'); // 30 seconds
        }

        return $response;
    }
}
