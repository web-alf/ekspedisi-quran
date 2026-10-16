<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service to monitor concurrency issues and performance metrics
 * in the warehouse packing system
 */
class ConcurrencyMonitorService
{
    const CACHE_PREFIX = 'concurrency_monitor:';
    const METRICS_TTL = 3600; // 1 hour

    /**
     * Record a concurrent access attempt
     */
    public function recordConcurrentAccess($resource, $userId, $action)
    {
        $key = self::CACHE_PREFIX . "access:{$resource}";
        $timestamp = microtime(true);
        
        $accessData = [
            'user_id' => $userId,
            'action' => $action,
            'timestamp' => $timestamp,
            'pid' => getmypid()
        ];
        
        // Store concurrent access attempt
        $attempts = Cache::get($key, []);
        $attempts[] = $accessData;
        
        // Keep only last 100 attempts
        if (count($attempts) > 100) {
            $attempts = array_slice($attempts, -100);
        }
        
        Cache::put($key, $attempts, self::METRICS_TTL);
        
        // Log potential race condition
        $recentAttempts = collect($attempts)
            ->where('timestamp', '>', $timestamp - 1) // Within 1 second
            ->count();
            
        if ($recentAttempts > 3) {
            Log::warning('Potential race condition detected', [
                'resource' => $resource,
                'concurrent_attempts' => $recentAttempts,
                'user_id' => $userId,
                'action' => $action
            ]);
        }
        
        return $accessData;
    }
    
    /**
     * Record a successful transaction completion
     */
    public function recordSuccessfulTransaction($resource, $userId, $executionTime)
    {
        $key = self::CACHE_PREFIX . "success:{$resource}";
        
        $successData = [
            'user_id' => $userId,
            'execution_time' => $executionTime,
            'timestamp' => microtime(true)
        ];
        
        $successes = Cache::get($key, []);
        $successes[] = $successData;
        
        // Keep only last 50 successes
        if (count($successes) > 50) {
            $successes = array_slice($successes, -50);
        }
        
        Cache::put($key, $successes, self::METRICS_TTL);
    }
    
    /**
     * Record a failed transaction (deadlock, timeout, etc.)
     */
    public function recordFailedTransaction($resource, $userId, $error, $attemptNumber = 1)
    {
        $key = self::CACHE_PREFIX . "failed:{$resource}";
        
        $failureData = [
            'user_id' => $userId,
            'error' => $error,
            'attempt_number' => $attemptNumber,
            'timestamp' => microtime(true)
        ];
        
        $failures = Cache::get($key, []);
        $failures[] = $failureData;
        
        // Keep only last 50 failures
        if (count($failures) > 50) {
            $failures = array_slice($failures, -50);
        }
        
        Cache::put($key, $failures, self::METRICS_TTL);
        
        // Log critical failures
        if ($attemptNumber >= 3) {
            Log::error('Transaction failed after multiple retries', [
                'resource' => $resource,
                'user_id' => $userId,
                'error' => $error,
                'attempts' => $attemptNumber
            ]);
        }
    }
    
    /**
     * Get concurrency metrics for a resource
     */
    public function getMetrics($resource)
    {
        $accesses = Cache::get(self::CACHE_PREFIX . "access:{$resource}", []);
        $successes = Cache::get(self::CACHE_PREFIX . "success:{$resource}", []);
        $failures = Cache::get(self::CACHE_PREFIX . "failed:{$resource}", []);
        
        $now = microtime(true);
        $lastHour = $now - 3600;
        
        // Filter last hour
        $recentAccesses = collect($accesses)->where('timestamp', '>', $lastHour);
        $recentSuccesses = collect($successes)->where('timestamp', '>', $lastHour);
        $recentFailures = collect($failures)->where('timestamp', '>', $lastHour);
        
        return [
            'resource' => $resource,
            'last_hour' => [
                'total_attempts' => $recentAccesses->count(),
                'successful_transactions' => $recentSuccesses->count(),
                'failed_transactions' => $recentFailures->count(),
                'success_rate' => $recentAccesses->count() > 0 
                    ? round(($recentSuccesses->count() / $recentAccesses->count()) * 100, 2)
                    : 0,
                'avg_execution_time' => $recentSuccesses->avg('execution_time'),
                'peak_concurrency' => $this->calculatePeakConcurrency($recentAccesses),
                'deadlock_count' => $recentFailures->filter(function($f) {
                    return str_contains(strtolower($f['error']), 'deadlock');
                })->count()
            ],
            'current_status' => $this->getCurrentStatus($resource)
        ];
    }
    
    /**
     * Calculate peak concurrency in last hour
     */
    private function calculatePeakConcurrency($accesses)
    {
        if ($accesses->isEmpty()) {
            return 0;
        }
        
        $peaks = [];
        $windowSize = 5; // 5 second windows
        
        $groupedAccesses = $accesses->groupBy(function($access) use ($windowSize) {
            return floor($access['timestamp'] / $windowSize) * $windowSize;
        });
        
        foreach ($groupedAccesses as $window => $windowAccesses) {
            $peaks[] = $windowAccesses->count();
        }
        
        return max($peaks);
    }
    
    /**
     * Get current status of resource
     */
    private function getCurrentStatus($resource)
    {
        $recentWindow = microtime(true) - 30; // Last 30 seconds
        $accesses = Cache::get(self::CACHE_PREFIX . "access:{$resource}", []);
        
        $recentActivity = collect($accesses)
            ->where('timestamp', '>', $recentWindow)
            ->count();
            
        if ($recentActivity > 10) {
            return 'high_activity';
        } elseif ($recentActivity > 3) {
            return 'moderate_activity';
        } elseif ($recentActivity > 0) {
            return 'low_activity';
        } else {
            return 'idle';
        }
    }
    
    /**
     * Get system-wide concurrency health check
     */
    public function getSystemHealthCheck()
    {
        $resources = ['box_access', 'item_packing', 'task_assignment'];
        $overallMetrics = [];
        
        foreach ($resources as $resource) {
            $metrics = $this->getMetrics($resource);
            $overallMetrics[$resource] = $metrics;
        }
        
        // Calculate overall health score
        $totalAttempts = collect($overallMetrics)->sum('last_hour.total_attempts');
        $totalSuccesses = collect($overallMetrics)->sum('last_hour.successful_transactions');
        $totalFailures = collect($overallMetrics)->sum('last_hour.failed_transactions');
        
        $overallSuccessRate = $totalAttempts > 0 ? ($totalSuccesses / $totalAttempts) * 100 : 100;
        
        $healthStatus = 'healthy';
        if ($overallSuccessRate < 90) {
            $healthStatus = 'degraded';
        }
        if ($overallSuccessRate < 70) {
            $healthStatus = 'unhealthy';
        }
        
        return [
            'health_status' => $healthStatus,
            'overall_success_rate' => round($overallSuccessRate, 2),
            'total_attempts_last_hour' => $totalAttempts,
            'total_failures_last_hour' => $totalFailures,
            'resources' => $overallMetrics,
            'recommendations' => $this->getRecommendations($overallMetrics)
        ];
    }
    
    /**
     * Get performance recommendations based on metrics
     */
    private function getRecommendations($metrics)
    {
        $recommendations = [];
        
        foreach ($metrics as $resource => $data) {
            $successRate = $data['last_hour']['success_rate'];
            $peakConcurrency = $data['last_hour']['peak_concurrency'];
            $deadlockCount = $data['last_hour']['deadlock_count'];
            
            if ($successRate < 90) {
                $recommendations[] = "Consider optimizing {$resource} operations - success rate is {$successRate}%";
            }
            
            if ($peakConcurrency > 20) {
                $recommendations[] = "High concurrency detected for {$resource} - consider implementing queue system";
            }
            
            if ($deadlockCount > 5) {
                $recommendations[] = "Multiple deadlocks detected for {$resource} - review locking strategy";
            }
        }
        
        if (empty($recommendations)) {
            $recommendations[] = "System performance is optimal";
        }
        
        return $recommendations;
    }
    
    /**
     * Clear metrics for a resource
     */
    public function clearMetrics($resource = null)
    {
        if ($resource) {
            Cache::forget(self::CACHE_PREFIX . "access:{$resource}");
            Cache::forget(self::CACHE_PREFIX . "success:{$resource}");
            Cache::forget(self::CACHE_PREFIX . "failed:{$resource}");
        } else {
            // Clear all metrics
            $keys = Cache::getRedis()->keys(self::CACHE_PREFIX . '*');
            foreach ($keys as $key) {
                Cache::forget(str_replace(config('cache.prefix') . ':', '', $key));
            }
        }
    }
}