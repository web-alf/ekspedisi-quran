<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PerformanceMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PerformanceController extends Controller
{
    protected $performanceService;

    public function __construct(PerformanceMonitoringService $performanceService)
    {
        $this->performanceService = $performanceService;
    }

    /**
     * Display performance monitoring dashboard
     */
    public function index()
    {
        $metrics = $this->performanceService->getDashboardMetrics();
        
        // Add additional real-time metrics
        $additionalMetrics = [
            'database_status' => $this->getDatabaseStatus(),
            'queue_status' => $this->getQueueStatus(),
            'storage_status' => $this->getStorageStatus(),
            'recent_activities' => $this->getRecentActivities()
        ];

        return Inertia::render('Admin/Performance/Dashboard', [
            'metrics' => array_merge($metrics, $additionalMetrics),
            'alerts' => $this->getActiveAlerts(),
            'recommendations' => $this->getPerformanceRecommendations()
        ]);
    }

    /**
     * Get API performance data
     */
    public function apiMetrics(Request $request)
    {
        $timeframe = $request->get('timeframe', '24h');
        
        return response()->json([
            'api_stats' => $this->performanceService->getApiStats(),
            'response_times' => $this->getResponseTimeData($timeframe),
            'error_rates' => $this->getErrorRateData($timeframe),
            'throughput' => $this->getThroughputData($timeframe)
        ]);
    }

    /**
     * Get system health status
     */
    public function healthCheck()
    {
        $health = [
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
            'storage' => $this->checkStorageHealth(),
            'memory' => $this->checkMemoryHealth(),
            'queue' => $this->checkQueueHealth()
        ];

        $overallStatus = collect($health)->every(fn($status) => $status['status'] === 'healthy') 
            ? 'healthy' : 'unhealthy';

        return response()->json([
            'overall_status' => $overallStatus,
            'components' => $health,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Clear performance cache
     */
    public function clearCache()
    {
        // Clear performance-related cache
        $patterns = [
            'api_stats:*',
            'slow_queries:*',
            'error_summary:*',
            'dashboard_stats_*'
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }

        return response()->json([
            'success' => true,
            'message' => 'Performance cache cleared successfully'
        ]);
    }

    /**
     * Export performance report
     */
    public function exportReport(Request $request)
    {
        $format = $request->get('format', 'json');
        $timeframe = $request->get('timeframe', '24h');
        
        $report = [
            'generated_at' => now()->toISOString(),
            'timeframe' => $timeframe,
            'metrics' => $this->performanceService->getDashboardMetrics(),
            'health_status' => $this->getHealthStatus(),
            'recommendations' => $this->getPerformanceRecommendations()
        ];

        if ($format === 'csv') {
            return $this->exportToCsv($report);
        }

        return response()->json($report);
    }

    /**
     * Get database status
     */
    private function getDatabaseStatus()
    {
        try {
            $start = microtime(true);
            $result = DB::select('SELECT 1');
            $responseTime = (microtime(true) - $start) * 1000;

            $tables = DB::select("SELECT table_name, table_rows, data_length 
                                FROM information_schema.tables 
                                WHERE table_schema = ?", [config('database.connections.mysql.database')]);

            return [
                'status' => 'connected',
                'response_time_ms' => round($responseTime, 2),
                'total_tables' => count($tables),
                'total_rows' => array_sum(array_column($tables, 'table_rows')),
                'database_size_mb' => round(array_sum(array_column($tables, 'data_length')) / 1024 / 1024, 2)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get queue status
     */
    private function getQueueStatus()
    {
        try {
            // Get failed jobs count
            $failedJobs = DB::table('failed_jobs')->count();
            
            return [
                'status' => 'active',
                'failed_jobs' => $failedJobs,
                'pending_jobs' => 0, // Would need to check queue driver for actual count
                'processed_today' => Cache::get('jobs_processed_today', 0)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get storage status
     */
    private function getStorageStatus()
    {
        $storagePaths = [
            'app' => storage_path('app'),
            'logs' => storage_path('logs'),
            'cache' => storage_path('framework/cache'),
            'sessions' => storage_path('framework/sessions')
        ];

        $status = [];
        foreach ($storagePaths as $name => $path) {
            if (is_dir($path)) {
                $size = $this->getDirectorySize($path);
                $status[$name] = [
                    'size_mb' => round($size / 1024 / 1024, 2),
                    'writable' => is_writable($path)
                ];
            }
        }

        return $status;
    }

    /**
     * Get recent activities for monitoring
     */
    private function getRecentActivities()
    {
        return [
            'recent_errors' => $this->getRecentErrors(),
            'slow_queries' => array_slice($this->performanceService->getSlowQueries(), -5),
            'high_memory_operations' => $this->getHighMemoryOperations()
        ];
    }

    /**
     * Get active performance alerts
     */
    private function getActiveAlerts()
    {
        $alerts = [];
        
        // Check for high error rates
        $errorSummary = Cache::get('error_summary:' . now()->format('Y-m-d'), []);
        if (isset($errorSummary['total_errors']) && $errorSummary['total_errors'] > 50) {
            $alerts[] = [
                'type' => 'error',
                'level' => 'warning',
                'message' => "High error count today: {$errorSummary['total_errors']} errors",
                'timestamp' => now()->toISOString()
            ];
        }

        // Check memory usage
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        if ($memoryUsage > 128) {
            $alerts[] = [
                'type' => 'memory',
                'level' => 'warning',
                'message' => "High memory usage: " . round($memoryUsage, 2) . "MB",
                'timestamp' => now()->toISOString()
            ];
        }

        return $alerts;
    }

    /**
     * Get performance recommendations
     */
    private function getPerformanceRecommendations()
    {
        $recommendations = [];
        
        // Check slow queries
        $slowQueries = $this->performanceService->getSlowQueries();
        if (count($slowQueries) > 10) {
            $recommendations[] = [
                'type' => 'database',
                'priority' => 'high',
                'title' => 'Optimize Database Queries',
                'description' => 'Multiple slow queries detected. Consider adding indexes or optimizing query logic.',
                'action' => 'Review slow query log and optimize'
            ];
        }

        // Check cache usage
        $cacheHitRate = Cache::get('cache_hit_rate', 85);
        if ($cacheHitRate < 80) {
            $recommendations[] = [
                'type' => 'cache',
                'priority' => 'medium',
                'title' => 'Improve Cache Strategy',
                'description' => "Cache hit rate is {$cacheHitRate}%. Consider implementing more aggressive caching.",
                'action' => 'Review and expand caching strategy'
            ];
        }

        return $recommendations;
    }

    // Helper methods
    private function checkDatabaseHealth()
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $responseTime = (microtime(true) - $start) * 1000;
            
            return [
                'status' => $responseTime < 100 ? 'healthy' : 'slow',
                'response_time_ms' => round($responseTime, 2)
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkCacheHealth()
    {
        try {
            Cache::put('health_check', 'test', 10);
            $value = Cache::get('health_check');
            Cache::forget('health_check');
            
            return ['status' => $value === 'test' ? 'healthy' : 'unhealthy'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkStorageHealth()
    {
        $storagePath = storage_path();
        return [
            'status' => is_writable($storagePath) ? 'healthy' : 'unhealthy',
            'writable' => is_writable($storagePath)
        ];
    }

    private function checkMemoryHealth()
    {
        $usage = memory_get_usage(true) / 1024 / 1024;
        return [
            'status' => $usage < 128 ? 'healthy' : 'warning',
            'usage_mb' => round($usage, 2)
        ];
    }

    private function checkQueueHealth()
    {
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            return [
                'status' => $failedJobs < 10 ? 'healthy' : 'warning',
                'failed_jobs' => $failedJobs
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function getDirectorySize($directory)
    {
        $size = 0;
        if (is_dir($directory)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    private function getRecentErrors()
    {
        // This would typically read from log files or error tracking service
        return Cache::get('recent_errors', []);
    }

    private function getHighMemoryOperations()
    {
        return Cache::get('high_memory_ops', []);
    }

    private function getResponseTimeData($timeframe)
    {
        // Implementation would depend on your specific monitoring needs
        return [];
    }

    private function getErrorRateData($timeframe)
    {
        // Implementation would depend on your specific monitoring needs
        return [];
    }

    private function getThroughputData($timeframe)
    {
        // Implementation would depend on your specific monitoring needs
        return [];
    }

    private function getHealthStatus()
    {
        return [
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
            'storage' => $this->checkStorageHealth(),
            'memory' => $this->checkMemoryHealth(),
            'queue' => $this->checkQueueHealth()
        ];
    }

    private function exportToCsv($data)
    {
        // CSV export implementation
        $filename = 'performance_report_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function() use ($data) {
            $handle = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($handle, ['Metric', 'Value', 'Timestamp']);
            
            // Add data rows (simplified)
            foreach ($data['metrics']['system_health'] as $metric => $value) {
                fputcsv($handle, [$metric, json_encode($value), $data['generated_at']]);
            }
            
            fclose($handle);
        }, 200, $headers);
    }
}