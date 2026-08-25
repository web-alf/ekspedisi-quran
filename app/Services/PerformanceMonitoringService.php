<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerformanceMonitoringService
{
    const SLOW_QUERY_THRESHOLD = 1000; // 1 second in milliseconds

    const MEMORY_THRESHOLD = 128; // 128MB

    const ERROR_RATE_THRESHOLD = 5; // 5% error rate

    /**
     * Monitor database query performance
     */
    public function monitorQuery(string $sql, float $executionTime, array $bindings = [])
    {
        // Log slow queries
        if ($executionTime * 1000 > self::SLOW_QUERY_THRESHOLD) {
            Log::warning('Slow query detected', [
                'sql' => $sql,
                'execution_time_ms' => round($executionTime * 1000, 2),
                'bindings' => $bindings,
                'memory_usage' => $this->formatBytes(memory_get_usage()),
                'timestamp' => now()->toISOString(),
            ]);

            // Store slow query stats
            $this->recordSlowQuery($sql, $executionTime);
        }
    }

    /**
     * Monitor memory usage
     */
    public function monitorMemory(string $operation = 'unknown')
    {
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024; // MB

        if ($memoryUsage > self::MEMORY_THRESHOLD) {
            Log::warning('High memory usage detected', [
                'operation' => $operation,
                'current_memory_mb' => round($memoryUsage, 2),
                'peak_memory_mb' => round($peakMemory, 2),
                'timestamp' => now()->toISOString(),
            ]);
        }

        return [
            'current' => round($memoryUsage, 2),
            'peak' => round($peakMemory, 2),
        ];
    }

    /**
     * Monitor API response times
     */
    public function monitorApiResponse(string $endpoint, float $responseTime, int $statusCode)
    {
        $isError = $statusCode >= 400;

        Log::info('API Response', [
            'endpoint' => $endpoint,
            'response_time_ms' => round($responseTime * 1000, 2),
            'status_code' => $statusCode,
            'is_error' => $isError,
            'timestamp' => now()->toISOString(),
        ]);

        // Store in cache for analytics
        $cacheKey = 'api_stats:'.md5($endpoint).':'.now()->format('Y-m-d-H');
        $stats = Cache::get($cacheKey, [
            'total_requests' => 0,
            'total_response_time' => 0,
            'error_count' => 0,
            'endpoint' => $endpoint,
            'hour' => now()->format('Y-m-d H:00'),
        ]);

        $stats['total_requests']++;
        $stats['total_response_time'] += $responseTime;
        if ($isError) {
            $stats['error_count']++;
        }

        $stats['avg_response_time'] = $stats['total_response_time'] / $stats['total_requests'];
        $stats['error_rate'] = ($stats['error_count'] / $stats['total_requests']) * 100;

        Cache::put($cacheKey, $stats, now()->addHours(25)); // Store for 25 hours

        // Alert on high error rate
        if ($stats['error_rate'] > self::ERROR_RATE_THRESHOLD && $stats['total_requests'] > 10) {
            Log::alert('High API error rate detected', [
                'endpoint' => $endpoint,
                'error_rate' => round($stats['error_rate'], 2),
                'total_requests' => $stats['total_requests'],
                'error_count' => $stats['error_count'],
            ]);
        }
    }

    /**
     * Get performance dashboard data
     */
    public function getDashboardMetrics()
    {
        $now = now();
        $metrics = [];

        // Recent slow queries
        $metrics['slow_queries'] = $this->getSlowQueries();

        // API performance stats
        $metrics['api_stats'] = $this->getApiStats();

        // System health
        $metrics['system_health'] = [
            'memory_usage' => $this->getCurrentMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'database_connections' => $this->getDatabaseConnections(),
        ];

        // Error logs summary
        $metrics['error_summary'] = $this->getErrorSummary();

        return $metrics;
    }

    /**
     * Record slow query for analytics
     */
    private function recordSlowQuery(string $sql, float $executionTime)
    {
        $cacheKey = 'slow_queries:'.now()->format('Y-m-d');
        $queries = Cache::get($cacheKey, []);

        $queries[] = [
            'sql' => substr($sql, 0, 200).(strlen($sql) > 200 ? '...' : ''),
            'execution_time' => round($executionTime * 1000, 2),
            'timestamp' => now()->toISOString(),
        ];

        // Keep only last 50 slow queries per day
        if (count($queries) > 50) {
            $queries = array_slice($queries, -50);
        }

        Cache::put($cacheKey, $queries, now()->addDay());
    }

    /**
     * Get recent slow queries
     */
    private function getSlowQueries()
    {
        $today = Cache::get('slow_queries:'.now()->format('Y-m-d'), []);
        $yesterday = Cache::get('slow_queries:'.now()->subDay()->format('Y-m-d'), []);

        return array_slice(array_merge($yesterday, $today), -20);
    }

    /**
     * Get API performance statistics
     */
    private function getApiStats()
    {
        $stats = [];
        $now = now();

        // Get stats for last 24 hours
        for ($i = 0; $i < 24; $i++) {
            $hour = $now->copy()->subHours($i);
            $pattern = 'api_stats:*:'.$hour->format('Y-m-d-H');

            // Get all API stats for this hour
            $hourStats = Cache::get($pattern, []);
            if (! empty($hourStats)) {
                $stats[] = $hourStats;
            }
        }

        return array_slice($stats, 0, 10); // Return last 10 hours of data
    }

    /**
     * Get current memory usage
     */
    private function getCurrentMemoryUsage()
    {
        return [
            'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'limit_mb' => $this->getPhpMemoryLimit(),
        ];
    }

    /**
     * Get disk usage
     */
    private function getDiskUsage()
    {
        $storagePath = storage_path();
        $freeBytes = disk_free_space($storagePath);
        $totalBytes = disk_total_space($storagePath);
        $usedBytes = $totalBytes - $freeBytes;

        return [
            'used_gb' => round($usedBytes / 1024 / 1024 / 1024, 2),
            'free_gb' => round($freeBytes / 1024 / 1024 / 1024, 2),
            'total_gb' => round($totalBytes / 1024 / 1024 / 1024, 2),
            'usage_percentage' => round(($usedBytes / $totalBytes) * 100, 2),
        ];
    }

    /**
     * Get cache hit rate (simplified)
     */
    private function getCacheHitRate()
    {
        // This would need Redis/Memcached stats for real implementation
        // For now, return estimated based on cache operations
        return Cache::get('cache_hit_rate', 85.5);
    }

    /**
     * Get database connections count
     */
    private function getDatabaseConnections()
    {
        try {
            $connections = DB::select("SHOW STATUS LIKE 'Threads_connected'");

            return isset($connections[0]) ? (int) $connections[0]->Value : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get error summary from logs
     */
    private function getErrorSummary()
    {
        $cacheKey = 'error_summary:'.now()->format('Y-m-d');

        return Cache::get($cacheKey, [
            'total_errors' => 0,
            'critical_errors' => 0,
            'warning_count' => 0,
            'last_error' => null,
        ]);
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    /**
     * Get PHP memory limit
     */
    private function getPhpMemoryLimit()
    {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit == -1) {
            return 'Unlimited';
        }

        $value = (int) $memoryLimit;
        $unit = strtoupper(substr($memoryLimit, -1));

        switch ($unit) {
            case 'G':
                $value *= 1024;
            case 'M':
                $value *= 1024;
            case 'K':
                $value *= 1024;
        }

        return round($value / 1024 / 1024, 2);
    }

    /**
     * Record application error
     */
    public function recordError(string $level, string $message, array $context = [])
    {
        $cacheKey = 'error_summary:'.now()->format('Y-m-d');
        $summary = Cache::get($cacheKey, [
            'total_errors' => 0,
            'critical_errors' => 0,
            'warning_count' => 0,
            'last_error' => null,
        ]);

        $summary['total_errors']++;
        if (in_array($level, ['critical', 'emergency', 'alert'])) {
            $summary['critical_errors']++;
        }
        if ($level === 'warning') {
            $summary['warning_count']++;
        }

        $summary['last_error'] = [
            'level' => $level,
            'message' => substr($message, 0, 100),
            'timestamp' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $summary, now()->addDay());
    }

    /**
     * Record query optimization data
     */
    public function recordQueryOptimization(array $optimizationData): void
    {
        $cacheKey = 'query_optimization:'.now()->format('Y-m-d-H');
        $data = Cache::get($cacheKey, [
            'n_plus_one_patterns' => 0,
            'slow_queries' => 0,
            'missing_indexes' => 0,
            'total_optimizations' => 0,
            'avg_optimization_score' => 0,
            'last_updated' => now()->toISOString(),
        ]);

        $data['n_plus_one_patterns'] += $optimizationData['n_plus_one_patterns'] ?? 0;
        $data['slow_queries'] += $optimizationData['slow_queries'] ?? 0;
        $data['missing_indexes'] += $optimizationData['missing_indexes'] ?? 0;
        $data['total_optimizations']++;

        // Update average optimization score
        if (isset($optimizationData['optimization_score'])) {
            $currentAvg = $data['avg_optimization_score'];
            $newScore = $optimizationData['optimization_score'];
            $data['avg_optimization_score'] = (($currentAvg * ($data['total_optimizations'] - 1)) + $newScore) / $data['total_optimizations'];
        }

        $data['last_updated'] = now()->toISOString();

        Cache::put($cacheKey, $data, now()->addHours(25));
    }

    /**
     * Get query optimization metrics for dashboard
     */
    public function getQueryOptimizationMetrics(): array
    {
        $metrics = [];
        $now = now();

        // Get metrics for last 24 hours
        for ($i = 0; $i < 24; $i++) {
            $hour = $now->copy()->subHours($i);
            $cacheKey = 'query_optimization:'.$hour->format('Y-m-d-H');
            $hourData = Cache::get($cacheKey);

            if ($hourData) {
                $metrics[] = array_merge($hourData, [
                    'hour' => $hour->format('Y-m-d H:00'),
                ]);
            }
        }

        return $metrics;
    }

    /**
     * Record N+1 query detection
     */
    public function recordNPlusOneDetection(array $patterns): void
    {
        foreach ($patterns as $pattern) {
            Log::warning('N+1 Query Pattern Detected', [
                'pattern' => $pattern['pattern'],
                'query_count' => $pattern['query_count'],
                'total_time_ms' => $pattern['total_time'],
                'severity' => $pattern['severity'],
                'suggestion' => $pattern['suggestion'],
                'performance_impact' => 'High - Database query optimization needed',
            ]);
        }

        // Update daily N+1 statistics
        $cacheKey = 'n_plus_one_stats:'.now()->format('Y-m-d');
        $stats = Cache::get($cacheKey, [
            'total_patterns' => 0,
            'critical_patterns' => 0,
            'total_queries_affected' => 0,
            'potential_time_savings' => 0,
            'last_detection' => null,
        ]);

        foreach ($patterns as $pattern) {
            $stats['total_patterns']++;
            $stats['total_queries_affected'] += $pattern['query_count'];
            $stats['potential_time_savings'] += $pattern['potential_savings'] ?? 0;

            if ($pattern['severity'] === 'critical' || $pattern['severity'] === 'high') {
                $stats['critical_patterns']++;
            }
        }

        $stats['last_detection'] = now()->toISOString();

        Cache::put($cacheKey, $stats, now()->addDay());
    }

    /**
     * Record index optimization recommendations
     */
    public function recordIndexRecommendations(array $recommendations): void
    {
        $totalRecommendations = 0;
        $criticalCount = 0;
        $highCount = 0;

        foreach ($recommendations as $table => $indexes) {
            foreach ($indexes as $index) {
                $totalRecommendations++;

                if ($index['priority'] === 'critical') {
                    $criticalCount++;
                } elseif ($index['priority'] === 'high') {
                    $highCount++;
                }

                // Log high priority index recommendations
                if (in_array($index['priority'], ['critical', 'high'])) {
                    Log::warning('Missing Database Index Detected', [
                        'table' => $table,
                        'column' => implode(', ', $index['columns']),
                        'priority' => $index['priority'],
                        'reason' => $index['reason'],
                        'suggested_index' => $index['index_name'],
                        'performance_impact' => $index['estimated_impact'],
                    ]);
                }
            }
        }

        // Update daily index statistics
        $cacheKey = 'index_recommendations:'.now()->format('Y-m-d');
        $stats = Cache::get($cacheKey, [
            'total_recommendations' => 0,
            'critical_recommendations' => 0,
            'high_priority_recommendations' => 0,
            'affected_tables' => 0,
            'last_analysis' => null,
        ]);

        $stats['total_recommendations'] += $totalRecommendations;
        $stats['critical_recommendations'] += $criticalCount;
        $stats['high_priority_recommendations'] += $highCount;
        $stats['affected_tables'] = count($recommendations);
        $stats['last_analysis'] = now()->toISOString();

        Cache::put($cacheKey, $stats, now()->addDay());
    }

    /**
     * Monitor query execution with optimization analysis
     */
    public function monitorQueryWithOptimization(string $sql, float $executionTime, array $analysis): void
    {
        // Call existing query monitoring
        $this->monitorQuery($sql, $executionTime);

        // Record optimization-specific data
        if ($analysis['optimization_score'] < 70 || ! empty($analysis['issues'])) {
            Log::info('Query Optimization Opportunity', [
                'sql' => substr($sql, 0, 200),
                'execution_time_ms' => round($executionTime * 1000, 2),
                'optimization_score' => $analysis['optimization_score'],
                'issues' => $analysis['issues'],
                'recommendations' => $analysis['recommendations'],
                'severity' => $analysis['severity'],
            ]);

            // Update optimization opportunity cache
            $this->updateOptimizationOpportunities($analysis);
        }
    }

    /**
     * Update optimization opportunities cache
     */
    private function updateOptimizationOpportunities(array $analysis): void
    {
        $cacheKey = 'optimization_opportunities:'.now()->format('Y-m-d');
        $opportunities = Cache::get($cacheKey, [
            'total_opportunities' => 0,
            'select_star_issues' => 0,
            'join_optimization_needed' => 0,
            'index_hints_needed' => 0,
            'query_rewrite_suggestions' => 0,
            'last_updated' => now()->toISOString(),
        ]);

        $opportunities['total_opportunities']++;

        foreach ($analysis['issues'] as $issue) {
            if (str_contains($issue, 'SELECT *')) {
                $opportunities['select_star_issues']++;
            } elseif (str_contains($issue, 'JOIN')) {
                $opportunities['join_optimization_needed']++;
            } elseif (str_contains($issue, 'index')) {
                $opportunities['index_hints_needed']++;
            }
        }

        if (count($analysis['recommendations']) > 0) {
            $opportunities['query_rewrite_suggestions']++;
        }

        $opportunities['last_updated'] = now()->toISOString();

        Cache::put($cacheKey, $opportunities, now()->addDay());
    }

    /**
     * Get comprehensive performance report including optimizations
     */
    public function getComprehensivePerformanceReport(): array
    {
        $baseMetrics = $this->getDashboardMetrics();

        // Add optimization metrics
        $optimizationMetrics = $this->getQueryOptimizationMetrics();
        $nPlusOneStats = Cache::get('n_plus_one_stats:'.now()->format('Y-m-d'), []);
        $indexStats = Cache::get('index_recommendations:'.now()->format('Y-m-d'), []);
        $optimizationOpportunities = Cache::get('optimization_opportunities:'.now()->format('Y-m-d'), []);

        return array_merge($baseMetrics, [
            'query_optimization' => [
                'hourly_metrics' => $optimizationMetrics,
                'n_plus_one_statistics' => $nPlusOneStats,
                'index_recommendations' => $indexStats,
                'optimization_opportunities' => $optimizationOpportunities,
            ],
            'optimization_summary' => [
                'total_n_plus_one_patterns' => $nPlusOneStats['total_patterns'] ?? 0,
                'critical_n_plus_one' => $nPlusOneStats['critical_patterns'] ?? 0,
                'missing_indexes' => $indexStats['total_recommendations'] ?? 0,
                'high_priority_indexes' => $indexStats['high_priority_recommendations'] ?? 0,
                'optimization_opportunities' => $optimizationOpportunities['total_opportunities'] ?? 0,
                'last_optimization_analysis' => max(
                    $nPlusOneStats['last_detection'] ?? '',
                    $indexStats['last_analysis'] ?? '',
                    $optimizationOpportunities['last_updated'] ?? ''
                ),
            ],
        ]);
    }

    /**
     * Alert on critical performance issues
     */
    public function alertCriticalPerformanceIssues(): void
    {
        $report = $this->getComprehensivePerformanceReport();
        $summary = $report['optimization_summary'];

        // Alert on critical N+1 patterns
        if ($summary['critical_n_plus_one'] > 0) {
            Log::alert('Critical N+1 Query Patterns Detected', [
                'critical_patterns' => $summary['critical_n_plus_one'],
                'total_patterns' => $summary['total_n_plus_one_patterns'],
                'recommendation' => 'Immediate attention required for eager loading optimization',
                'impact' => 'High - Database performance severely impacted',
            ]);
        }

        // Alert on high number of missing indexes
        if ($summary['high_priority_indexes'] > 5) {
            Log::alert('Multiple High-Priority Index Recommendations', [
                'high_priority_indexes' => $summary['high_priority_indexes'],
                'total_missing_indexes' => $summary['missing_indexes'],
                'recommendation' => 'Consider creating database indexes to improve query performance',
                'impact' => 'High - Query execution times will improve significantly',
            ]);
        }

        // Alert on optimization opportunities
        if ($summary['optimization_opportunities'] > 20) {
            Log::warning('High Number of Query Optimization Opportunities', [
                'total_opportunities' => $summary['optimization_opportunities'],
                'recommendation' => 'Review and optimize frequently executed queries',
                'impact' => 'Medium - Cumulative performance improvements available',
            ]);
        }
    }
}
