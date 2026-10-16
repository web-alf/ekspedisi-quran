<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class MetricsCollectionService
{
    protected array $config;

    protected array $metrics = [];

    public function __construct()
    {
        $this->config = config('monitoring.metrics', [
            'collection_interval' => 60, // seconds
            'retention_period' => 86400 * 7, // 7 days
            'alert_thresholds' => [
                'response_time' => 2000, // ms
                'memory_usage' => 512, // MB
                'cpu_usage' => 80, // percentage
                'error_rate' => 5, // percentage
                'queue_size' => 1000,
            ],
        ]);
    }

    /**
     * Collect all system metrics
     */
    public function collectMetrics(): array
    {
        $timestamp = now();

        $metrics = [
            'timestamp' => $timestamp->toISOString(),
            'system' => $this->collectSystemMetrics(),
            'application' => $this->collectApplicationMetrics(),
            'database' => $this->collectDatabaseMetrics(),
            'cache' => $this->collectCacheMetrics(),
            'queue' => $this->collectQueueMetrics(),
            'performance' => $this->collectPerformanceMetrics(),
            'business' => $this->collectBusinessMetrics(),
            'storage' => $this->collectStorageMetrics(),
            'errors' => $this->collectErrorMetrics(),
        ];

        // Store metrics for historical analysis
        $this->storeMetrics($metrics);

        // Check for alerts
        $alerts = $this->checkAlerts($metrics);
        if (! empty($alerts)) {
            $this->triggerAlerts($alerts, $metrics);
        }

        return $metrics;
    }

    /**
     * Collect system-level metrics
     */
    protected function collectSystemMetrics(): array
    {
        $loadAvg = sys_getloadavg();

        return [
            'memory' => [
                'usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'limit_mb' => $this->getPhpMemoryLimit(),
            ],
            'cpu' => [
                'load_1min' => $loadAvg[0] ?? 0,
                'load_5min' => $loadAvg[1] ?? 0,
                'load_15min' => $loadAvg[2] ?? 0,
            ],
            'disk' => $this->getDiskUsage(),
            'processes' => [
                'running' => $this->getProcessCount(),
            ],
        ];
    }

    /**
     * Collect application-specific metrics
     */
    protected function collectApplicationMetrics(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'uptime' => $this->getApplicationUptime(),
            'active_sessions' => $this->getActiveSessionsCount(),
            'logged_in_users' => $this->getLoggedInUsersCount(),
        ];
    }

    /**
     * Collect database metrics
     */
    protected function collectDatabaseMetrics(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $connectionTime = (microtime(true) - $start) * 1000;

            $connectionCount = $this->getDatabaseConnectionCount();
            $slowQueries = $this->getSlowQueriesCount();
            $tableStats = $this->getDatabaseTableStats();

            return [
                'connection_time_ms' => round($connectionTime, 2),
                'active_connections' => $connectionCount,
                'slow_queries_24h' => $slowQueries,
                'total_tables' => count($tableStats),
                'total_rows' => array_sum(array_column($tableStats, 'rows')),
                'database_size_mb' => round(array_sum(array_column($tableStats, 'size_mb')), 2),
                'status' => 'healthy',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'connection_time_ms' => null,
            ];
        }
    }

    /**
     * Collect cache metrics
     */
    protected function collectCacheMetrics(): array
    {
        try {
            // Test cache performance
            $start = microtime(true);
            $testKey = 'metrics_test_'.time();
            Cache::put($testKey, 'test_value', 10);
            $writeTime = (microtime(true) - $start) * 1000;

            $start = microtime(true);
            Cache::get($testKey);
            $readTime = (microtime(true) - $start) * 1000;

            Cache::forget($testKey);

            // Get Redis stats if available
            $redisStats = $this->getRedisStats();

            return [
                'write_time_ms' => round($writeTime, 2),
                'read_time_ms' => round($readTime, 2),
                'status' => 'healthy',
                'redis_stats' => $redisStats,
                'hit_rate' => $this->getCacheHitRate(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Collect queue metrics
     */
    protected function collectQueueMetrics(): array
    {
        try {
            $queues = ['high', 'certificates', 'warehouse', 'default', 'low'];
            $queueSizes = [];
            $totalJobs = 0;

            foreach ($queues as $queue) {
                $size = $this->getQueueSize($queue);
                $queueSizes[$queue] = $size;
                $totalJobs += $size;
            }

            $failedJobs = DB::table('failed_jobs')->count();
            $processingJobs = $this->getProcessingJobsCount();

            return [
                'queue_sizes' => $queueSizes,
                'total_jobs' => $totalJobs,
                'failed_jobs' => $failedJobs,
                'processing_jobs' => $processingJobs,
                'workers_active' => $this->getActiveWorkersCount(),
                'throughput_per_minute' => $this->getQueueThroughput(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Collect performance metrics
     */
    protected function collectPerformanceMetrics(): array
    {
        $metrics = Cache::get('performance_metrics_current', []);

        return [
            'avg_response_time_ms' => $metrics['avg_response_time'] ?? 0,
            'p95_response_time_ms' => $metrics['p95_response_time'] ?? 0,
            'requests_per_minute' => $metrics['requests_per_minute'] ?? 0,
            'error_rate_percentage' => $metrics['error_rate'] ?? 0,
            'slow_requests_count' => $metrics['slow_requests'] ?? 0,
        ];
    }

    /**
     * Collect business metrics
     */
    protected function collectBusinessMetrics(): array
    {
        return [
            'active_shipments' => DB::table('pengiriman')
                ->whereNotIn('status_id', function ($query) {
                    $query->select('id')->from('status_pengiriman')->where('is_final', true);
                })->count(),
            'completed_today' => DB::table('pengiriman')
                ->whereDate('updated_at', today())
                ->whereIn('status_id', function ($query) {
                    $query->select('id')->from('status_pengiriman')->where('is_final', true);
                })->count(),
            'pending_tasks' => DB::table('daily_packing_tasks')
                ->where('status', 'in_progress')->count(),
            'new_requests_today' => DB::table('mushaf_requests')
                ->whereDate('created_at', today())->count(),
            'active_users_last_hour' => $this->getActiveUsersLastHour(),
        ];
    }

    /**
     * Collect storage metrics
     */
    protected function collectStorageMetrics(): array
    {
        try {
            $storageService = app(\App\Services\StorageMonitoringService::class);
            $report = $storageService->generateReport();

            return [
                'usage_percentage' => $report['summary']['storage_usage'] ?? 0,
                'total_files' => $report['summary']['total_files'] ?? 0,
                'health_score' => $report['statistics']['health_score'] ?? 100,
                'alerts_count' => $report['summary']['alerts_count'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Collect error metrics
     */
    protected function collectErrorMetrics(): array
    {
        $errorSummary = Cache::get('error_summary:'.now()->format('Y-m-d'), [
            'total_errors' => 0,
            'critical_errors' => 0,
            'warning_count' => 0,
        ]);

        return [
            'total_errors_today' => $errorSummary['total_errors'],
            'critical_errors_today' => $errorSummary['critical_errors'],
            'warnings_today' => $errorSummary['warning_count'],
            'error_rate_last_hour' => $this->getErrorRateLastHour(),
        ];
    }

    /**
     * Store metrics for historical analysis
     */
    protected function storeMetrics(array $metrics): void
    {
        $cacheKey = 'metrics_history:'.now()->format('Y-m-d-H-i');

        try {
            Cache::put($cacheKey, $metrics, $this->config['retention_period']);

            // Also store aggregated hourly metrics
            $this->storeHourlyAggregates($metrics);
        } catch (\Exception $e) {
            Log::error('Failed to store metrics', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Store hourly aggregated metrics
     */
    protected function storeHourlyAggregates(array $metrics): void
    {
        $hourlyKey = 'metrics_hourly:'.now()->format('Y-m-d-H');

        try {
            $existing = Cache::get($hourlyKey, [
                'samples' => 0,
                'aggregates' => [],
            ]);

            $existing['samples']++;

            // Aggregate key metrics
            $this->aggregateMetric($existing['aggregates'], 'response_time', $metrics['performance']['avg_response_time_ms']);
            $this->aggregateMetric($existing['aggregates'], 'memory_usage', $metrics['system']['memory']['usage_mb']);
            $this->aggregateMetric($existing['aggregates'], 'queue_total', $metrics['queue']['total_jobs']);
            $this->aggregateMetric($existing['aggregates'], 'error_rate', $metrics['performance']['error_rate_percentage']);

            Cache::put($hourlyKey, $existing, 86400 * 7); // Keep for 7 days
        } catch (\Exception $e) {
            Log::warning('Failed to store hourly aggregates', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Helper to aggregate metric values
     */
    protected function aggregateMetric(array &$aggregates, string $key, $value): void
    {
        if (! isset($aggregates[$key])) {
            $aggregates[$key] = ['sum' => 0, 'min' => $value, 'max' => $value, 'count' => 0];
        }

        $aggregates[$key]['sum'] += $value;
        $aggregates[$key]['min'] = min($aggregates[$key]['min'], $value);
        $aggregates[$key]['max'] = max($aggregates[$key]['max'], $value);
        $aggregates[$key]['count']++;
        $aggregates[$key]['avg'] = $aggregates[$key]['sum'] / $aggregates[$key]['count'];
    }

    /**
     * Check for alert conditions
     */
    protected function checkAlerts(array $metrics): array
    {
        $alerts = [];
        $thresholds = $this->config['alert_thresholds'];

        // Response time alert
        if ($metrics['performance']['avg_response_time_ms'] > $thresholds['response_time']) {
            $alerts[] = [
                'type' => 'performance',
                'level' => 'warning',
                'metric' => 'response_time',
                'current' => $metrics['performance']['avg_response_time_ms'],
                'threshold' => $thresholds['response_time'],
                'message' => "High response time: {$metrics['performance']['avg_response_time_ms']}ms",
            ];
        }

        // Memory usage alert
        if ($metrics['system']['memory']['usage_mb'] > $thresholds['memory_usage']) {
            $alerts[] = [
                'type' => 'system',
                'level' => 'warning',
                'metric' => 'memory_usage',
                'current' => $metrics['system']['memory']['usage_mb'],
                'threshold' => $thresholds['memory_usage'],
                'message' => "High memory usage: {$metrics['system']['memory']['usage_mb']}MB",
            ];
        }

        // Queue size alert
        if ($metrics['queue']['total_jobs'] > $thresholds['queue_size']) {
            $alerts[] = [
                'type' => 'queue',
                'level' => 'warning',
                'metric' => 'queue_size',
                'current' => $metrics['queue']['total_jobs'],
                'threshold' => $thresholds['queue_size'],
                'message' => "High queue size: {$metrics['queue']['total_jobs']} jobs",
            ];
        }

        // Error rate alert
        if ($metrics['performance']['error_rate_percentage'] > $thresholds['error_rate']) {
            $alerts[] = [
                'type' => 'errors',
                'level' => 'critical',
                'metric' => 'error_rate',
                'current' => $metrics['performance']['error_rate_percentage'],
                'threshold' => $thresholds['error_rate'],
                'message' => "High error rate: {$metrics['performance']['error_rate_percentage']}%",
            ];
        }

        return $alerts;
    }

    /**
     * Trigger alerts
     */
    protected function triggerAlerts(array $alerts, array $metrics): void
    {
        foreach ($alerts as $alert) {
            Log::channel($alert['level'] === 'critical' ? 'single' : 'daily')->log(
                $alert['level'],
                'Monitoring Alert: '.$alert['message'],
                $alert
            );
        }

        // Store active alerts for dashboard
        Cache::put('monitoring_active_alerts', $alerts, 3600);

        // Send critical alerts via email or other channels
        $criticalAlerts = array_filter($alerts, fn ($alert) => $alert['level'] === 'critical');
        if (! empty($criticalAlerts)) {
            $this->sendCriticalAlerts($criticalAlerts, $metrics);
        }
    }

    /**
     * Send critical alerts
     */
    protected function sendCriticalAlerts(array $alerts, array $metrics): void
    {
        // This would integrate with your notification system
        // For now, just log them
        foreach ($alerts as $alert) {
            Log::critical('CRITICAL MONITORING ALERT', [
                'alert' => $alert,
                'system_state' => [
                    'memory_mb' => $metrics['system']['memory']['usage_mb'],
                    'queue_jobs' => $metrics['queue']['total_jobs'],
                    'response_time_ms' => $metrics['performance']['avg_response_time_ms'],
                    'error_rate' => $metrics['performance']['error_rate_percentage'],
                ],
            ]);
        }
    }

    /**
     * Get historical metrics
     */
    public function getHistoricalMetrics(string $period = '24h'): array
    {
        $minutes = match ($period) {
            '1h' => 60,
            '6h' => 360,
            '24h' => 1440,
            '7d' => 10080,
            default => 1440
        };

        $metrics = [];
        $now = now();

        for ($i = 0; $i < $minutes; $i += 60) { // Sample every hour
            $timestamp = $now->copy()->subMinutes($i);
            $key = 'metrics_hourly:'.$timestamp->format('Y-m-d-H');

            $hourlyData = Cache::get($key);
            if ($hourlyData) {
                $metrics[] = [
                    'timestamp' => $timestamp->toISOString(),
                    'data' => $hourlyData['aggregates'],
                ];
            }
        }

        return array_reverse($metrics);
    }

    // Helper methods for data collection

    protected function getPhpMemoryLimit()
    {
        $limit = ini_get('memory_limit');
        if ($limit == -1) {
            return 'unlimited';
        }

        $value = intval($limit);
        $unit = strtoupper(substr($limit, -1));

        return match ($unit) {
            'G' => $value * 1024,
            'M' => $value,
            'K' => $value / 1024,
            default => $value / 1024 / 1024
        };
    }

    protected function getDiskUsage(): array
    {
        $path = storage_path();
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        $used = $total - $free;

        return [
            'total_gb' => round($total / 1024 / 1024 / 1024, 2),
            'used_gb' => round($used / 1024 / 1024 / 1024, 2),
            'free_gb' => round($free / 1024 / 1024 / 1024, 2),
            'usage_percentage' => round(($used / $total) * 100, 2),
        ];
    }

    protected function getProcessCount(): int
    {
        try {
            return (int) shell_exec('ps aux | wc -l');
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function getApplicationUptime(): int
    {
        $bootFile = storage_path('framework/app_boot_time');
        if (file_exists($bootFile)) {
            return time() - (int) file_get_contents($bootFile);
        }

        return 0;
    }

    protected function getActiveSessionsCount(): int
    {
        try {
            return DB::table('sessions')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function getLoggedInUsersCount(): int
    {
        try {
            return DB::table('sessions')
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function getDatabaseConnectionCount(): int
    {
        try {
            $result = DB::select("SHOW STATUS LIKE 'Threads_connected'");

            return isset($result[0]) ? (int) $result[0]->Value : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function getSlowQueriesCount(): int
    {
        $queries = Cache::get('slow_queries:'.now()->format('Y-m-d'), []);

        return count($queries);
    }

    protected function getDatabaseTableStats(): array
    {
        try {
            $database = config('database.connections.'.config('database.default').'.database');
            if (! $database) {
                return [];
            }

            return DB::select('
                SELECT table_name, table_rows as rows, 
                       ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
                FROM information_schema.tables 
                WHERE table_schema = ?
            ', [$database]);
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function getRedisStats(): array
    {
        try {
            $redis = Redis::connection();
            $info = $redis->info('memory');

            return [
                'memory_used' => $info['used_memory_human'] ?? 'N/A',
                'memory_peak' => $info['used_memory_peak_human'] ?? 'N/A',
                'connected_clients' => $redis->info('clients')['connected_clients'] ?? 0,
            ];
        } catch (\Exception $e) {
            return ['status' => 'unavailable'];
        }
    }

    protected function getCacheHitRate(): float
    {
        // This would need actual cache statistics from Redis/Memcached
        return Cache::get('cache_hit_rate', 85.0);
    }

    protected function getQueueSize(string $queue): int
    {
        try {
            $redis = Redis::connection('queue');
            $queueKey = config('database.redis.options.prefix', '')."queues:{$queue}";

            return $redis->llen($queueKey);
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function getProcessingJobsCount(): int
    {
        try {
            return DB::table('job_batches')
                ->where('pending_jobs', '>', 0)
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function getActiveWorkersCount(): int
    {
        // This would need to be implemented based on your worker monitoring
        return Cache::get('active_workers_count', 0);
    }

    protected function getQueueThroughput(): int
    {
        $key = 'queue_throughput:'.now()->format('Y-m-d-H-i');

        return Cache::get($key, 0);
    }

    protected function getActiveUsersLastHour(): int
    {
        try {
            return DB::table('sessions')
                ->where('last_activity', '>=', now()->subHour()->timestamp)
                ->distinct('user_id')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function getErrorRateLastHour(): float
    {
        $errors = Cache::get('error_count_last_hour', 0);
        $requests = Cache::get('requests_count_last_hour', 1);

        return round(($errors / $requests) * 100, 2);
    }
}
