<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\PerformanceMonitoringService;
use Illuminate\Support\Facades\Log;

class PerformanceMonitoring
{
    protected $performanceService;

    public function __construct(PerformanceMonitoringService $performanceService)
    {
        $this->performanceService = $performanceService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        // Monitor request start
        $endpoint = $request->getPathInfo();
        $method = $request->getMethod();
        
        Log::info('Request Started', [
            'method' => $method,
            'endpoint' => $endpoint,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'memory_start' => $this->formatBytes($startMemory),
            'timestamp' => now()->toISOString()
        ]);

        $response = $next($request);

        // Monitor request completion
        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;
        $endMemory = memory_get_usage();
        $memoryUsed = $endMemory - $startMemory;
        
        // Record API performance
        $this->performanceService->monitorApiResponse(
            $endpoint,
            $responseTime,
            $response->getStatusCode()
        );
        
        // Monitor memory usage
        if ($memoryUsed > 50 * 1024 * 1024) { // 50MB threshold
            $this->performanceService->monitorMemory($endpoint);
        }
        
        Log::info('Request Completed', [
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => $response->getStatusCode(),
            'response_time_ms' => round($responseTime * 1000, 2),
            'memory_used' => $this->formatBytes($memoryUsed),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage()),
            'timestamp' => now()->toISOString()
        ]);

        return $response;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}