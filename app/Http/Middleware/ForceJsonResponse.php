<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Force JSON response for API endpoints
        if ($request->is('admin/scan-qr/*') || $request->expectsJson()) {
            $request->headers->set('Accept', 'application/json');
        }

        $response = $next($request);

        // If it's a scan-qr request and we got HTML response (error page)
        if ($request->is('admin/scan-qr/*') && 
            !$response->headers->get('Content-Type') !== 'application/json' &&
            $response->getStatusCode() >= 400) {
            
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred',
                'status_code' => $response->getStatusCode(),
                'debug' => app()->environment('local') ? 'Check Laravel logs for details' : null
            ], $response->getStatusCode());
        }

        return $response;
    }
}
