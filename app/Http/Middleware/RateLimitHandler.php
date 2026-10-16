<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as ResponseCode;

class RateLimitHandler
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if IP is whitelisted (bypass rate limiting)
        if ($this->isWhitelistedIP($request->ip())) {
            return $next($request);
        }

        // Log rate limit attempts for monitoring
        if ($request->hasHeader('X-RateLimit-Remaining') && $request->header('X-RateLimit-Remaining') < 10) {
            Log::warning('Rate limit approaching', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
                'remaining' => $request->header('X-RateLimit-Remaining'),
            ]);
        }

        return $next($request);
    }

    /**
     * Check if IP address is whitelisted for rate limiting bypass
     */
    private function isWhitelistedIP(string $ip): bool
    {
        $whitelistedIPs = config('rate-limiting.whitelist_ips', []);

        // Check for exact IP match
        if (in_array($ip, $whitelistedIPs)) {
            return true;
        }

        // Check for subnet matches (CIDR notation)
        foreach ($whitelistedIPs as $whitelistedIP) {
            if (strpos($whitelistedIP, '/') !== false) {
                if ($this->ipInRange($ip, $whitelistedIP)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if IP is in given range (CIDR)
     */
    private function ipInRange(string $ip, string $range): bool
    {
        [$range, $netmask] = explode('/', $range, 2);
        $range_decimal = ip2long($range);
        $ip_decimal = ip2long($ip);
        $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
        $netmask_decimal = ~$wildcard_decimal;

        return ($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal);
    }

    /**
     * Handle rate limit exceeded response
     */
    public function handleRateLimitExceeded(Request $request, int $retryAfter): Response
    {
        // Log rate limit violation
        Log::warning('Rate limit exceeded', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'method' => $request->method(),
            'user_id' => $request->user()?->id,
            'retry_after' => $retryAfter,
        ]);

        // Determine response format based on request
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $retryAfter,
                'retry_after_human' => $this->formatRetryAfter($retryAfter),
            ], ResponseCode::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => $retryAfter,
                'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
                'X-RateLimit-Limit' => config('rate-limiting.default_limit', 60),
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        // For web requests, redirect with error message
        return redirect()->back()
            ->withErrors(['rate_limit' => 'Too many requests. Please wait '.$this->formatRetryAfter($retryAfter).' before trying again.'])
            ->withInput();
    }

    /**
     * Format retry after seconds to human readable format
     */
    private function formatRetryAfter(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' seconds';
        } elseif ($seconds < 3600) {
            return ceil($seconds / 60).' minutes';
        } else {
            return ceil($seconds / 3600).' hours';
        }
    }
}
