<?php

namespace App\Console\Commands\Monitoring;

use App\Services\PerformanceMonitoringService;
use App\Services\StorageMonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SystemHealthCommand extends Command
{
    protected $signature = 'monitoring:health-check 
                            {--format=table : Output format (table, json, csv)}
                            {--export= : Export results to file}
                            {--threshold=warning : Alert threshold (info, warning, critical)}';

    protected $description = 'Perform comprehensive system health check';

    public function handle()
    {
        $format = $this->option('format');
        $exportFile = $this->option('export');
        $threshold = $this->option('threshold');

        $this->info('🏥 Performing System Health Check...');

        $healthData = $this->performHealthCheck();
        $overallStatus = $this->calculateOverallStatus($healthData);

        $this->displayResults($healthData, $overallStatus, $format);

        if ($exportFile) {
            $this->exportResults($healthData, $exportFile, $format);
        }

        // Return appropriate exit code
        return match ($overallStatus) {
            'healthy' => 0,
            'warning' => 1,
            'critical' => 2,
            default => 3
        };
    }

    protected function performHealthCheck(): array
    {
        $checks = [];

        // Database Health
        $checks['database'] = $this->checkDatabase();

        // Cache Health
        $checks['cache'] = $this->checkCache();

        // Storage Health
        $checks['storage'] = $this->checkStorage();

        // Queue Health
        $checks['queue'] = $this->checkQueue();

        // Performance Health
        $checks['performance'] = $this->checkPerformance();

        // System Resources
        $checks['system'] = $this->checkSystemResources();

        // Application Health
        $checks['application'] = $this->checkApplication();

        // Security Health
        $checks['security'] = $this->checkSecurity();

        return $checks;
    }

    protected function checkDatabase(): array
    {
        $this->line('🗄️ Checking database...');

        try {
            $start = microtime(true);
            $result = DB::select('SELECT 1 as test');
            $responseTime = (microtime(true) - $start) * 1000;

            $status = 'healthy';
            $issues = [];

            if ($responseTime > 1000) {
                $status = 'warning';
                $issues[] = 'Slow database response time';
            }

            // Check database size
            $databaseStats = $this->getDatabaseStats();

            return [
                'status' => $status,
                'response_time_ms' => round($responseTime, 2),
                'issues' => $issues,
                'stats' => $databaseStats,
                'message' => $status === 'healthy' ? 'Database is responding normally' : 'Database has performance issues',
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'error' => $e->getMessage(),
                'issues' => ['Database connection failed'],
                'message' => 'Database is not accessible',
            ];
        }
    }

    protected function checkCache(): array
    {
        $this->line('🗄️ Checking cache...');

        try {
            $testKey = 'health_check_'.time();
            $testValue = 'test_value';

            // Test write
            $start = microtime(true);
            Cache::put($testKey, $testValue, 60);
            $writeTime = (microtime(true) - $start) * 1000;

            // Test read
            $start = microtime(true);
            $readValue = Cache::get($testKey);
            $readTime = (microtime(true) - $start) * 1000;

            // Cleanup
            Cache::forget($testKey);

            $status = 'healthy';
            $issues = [];

            if ($writeTime > 100 || $readTime > 100) {
                $status = 'warning';
                $issues[] = 'Slow cache operations';
            }

            if ($readValue !== $testValue) {
                $status = 'critical';
                $issues[] = 'Cache read/write integrity issue';
            }

            return [
                'status' => $status,
                'write_time_ms' => round($writeTime, 2),
                'read_time_ms' => round($readTime, 2),
                'issues' => $issues,
                'message' => $status === 'healthy' ? 'Cache is working normally' : 'Cache has issues',
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'error' => $e->getMessage(),
                'issues' => ['Cache system not accessible'],
                'message' => 'Cache system is not working',
            ];
        }
    }

    protected function checkStorage(): array
    {
        $this->line('💾 Checking storage...');

        try {
            $storageService = app(StorageMonitoringService::class);
            $report = $storageService->generateReport();

            $usagePercentage = $report['summary']['storage_usage'] ?? 0;
            $healthScore = $report['statistics']['health_score'] ?? 100;
            $alertsCount = $report['summary']['alerts_count'] ?? 0;

            $status = 'healthy';
            $issues = [];

            if ($usagePercentage > 90) {
                $status = 'critical';
                $issues[] = 'Storage usage critical';
            } elseif ($usagePercentage > 80) {
                $status = 'warning';
                $issues[] = 'Storage usage high';
            }

            if ($healthScore < 80) {
                $status = 'warning';
                $issues[] = 'Storage health score low';
            }

            if ($alertsCount > 0) {
                $status = 'warning';
                $issues[] = 'Storage alerts active';
            }

            return [
                'status' => $status,
                'usage_percentage' => $usagePercentage,
                'health_score' => $healthScore,
                'alerts_count' => $alertsCount,
                'issues' => $issues,
                'message' => $status === 'healthy' ? 'Storage is healthy' : 'Storage needs attention',
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'error' => $e->getMessage(),
                'issues' => ['Storage monitoring failed'],
                'message' => 'Cannot assess storage health',
            ];
        }
    }

    protected function checkQueue(): array
    {
        $this->line('📦 Checking queue system...');

        try {
            $failedJobs = DB::table('failed_jobs')->count();
            $totalQueueSize = 0;

            $queues = ['high', 'certificates', 'warehouse', 'default', 'low'];
            foreach ($queues as $queue) {
                // This would need actual queue size checking
                $totalQueueSize += 0; // Placeholder
            }

            $status = 'healthy';
            $issues = [];

            if ($failedJobs > 10) {
                $status = 'warning';
                $issues[] = "High number of failed jobs: {$failedJobs}";
            }

            if ($totalQueueSize > 1000) {
                $status = 'warning';
                $issues[] = "Large queue backlog: {$totalQueueSize}";
            }

            return [
                'status' => $status,
                'failed_jobs' => $failedJobs,
                'total_queue_size' => $totalQueueSize,
                'issues' => $issues,
                'message' => $status === 'healthy' ? 'Queue system is healthy' : 'Queue system needs attention',
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'error' => $e->getMessage(),
                'issues' => ['Queue system check failed'],
                'message' => 'Cannot assess queue health',
            ];
        }
    }

    protected function checkPerformance(): array
    {
        $this->line('⚡ Checking performance...');

        try {
            $performanceService = app(PerformanceMonitoringService::class);
            $metrics = $performanceService->getDashboardMetrics();

            $status = 'healthy';
            $issues = [];

            // Check error rate
            if (isset($metrics['error_summary']['total_errors']) && $metrics['error_summary']['total_errors'] > 50) {
                $status = 'warning';
                $issues[] = 'High error count today';
            }

            // Check slow queries
            if (isset($metrics['slow_queries']) && count($metrics['slow_queries']) > 10) {
                $status = 'warning';
                $issues[] = 'Multiple slow queries detected';
            }

            // Check memory usage
            $memoryUsage = memory_get_usage(true) / 1024 / 1024;
            if ($memoryUsage > 256) {
                $status = 'warning';
                $issues[] = 'High memory usage';
            }

            return [
                'status' => $status,
                'memory_usage_mb' => round($memoryUsage, 2),
                'slow_queries_count' => count($metrics['slow_queries'] ?? []),
                'error_count_today' => $metrics['error_summary']['total_errors'] ?? 0,
                'issues' => $issues,
                'message' => $status === 'healthy' ? 'Performance is good' : 'Performance issues detected',
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'error' => $e->getMessage(),
                'issues' => ['Performance check failed'],
                'message' => 'Cannot assess performance',
            ];
        }
    }

    protected function checkSystemResources(): array
    {
        $this->line('💻 Checking system resources...');

        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        $memoryLimit = $this->getMemoryLimit();

        $diskTotal = disk_total_space(storage_path());
        $diskFree = disk_free_space(storage_path());
        $diskUsagePercent = (($diskTotal - $diskFree) / $diskTotal) * 100;

        $status = 'healthy';
        $issues = [];

        if ($memoryLimit > 0 && ($memoryUsage / $memoryLimit) > 0.8) {
            $status = 'warning';
            $issues[] = 'High memory usage';
        }

        if ($diskUsagePercent > 90) {
            $status = 'critical';
            $issues[] = 'Critical disk usage';
        } elseif ($diskUsagePercent > 80) {
            $status = 'warning';
            $issues[] = 'High disk usage';
        }

        return [
            'status' => $status,
            'memory_usage_mb' => round($memoryUsage, 2),
            'memory_limit_mb' => $memoryLimit,
            'disk_usage_percent' => round($diskUsagePercent, 2),
            'disk_free_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
            'issues' => $issues,
            'message' => $status === 'healthy' ? 'System resources are adequate' : 'System resources need attention',
        ];
    }

    protected function checkApplication(): array
    {
        $this->line('🚀 Checking application...');

        $status = 'healthy';
        $issues = [];

        // Check environment
        if (app()->environment('production') && config('app.debug')) {
            $status = 'warning';
            $issues[] = 'Debug mode enabled in production';
        }

        // Check important directories
        $directories = [
            storage_path(),
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
        ];

        foreach ($directories as $dir) {
            if (! is_writable($dir)) {
                $status = 'critical';
                $issues[] = "Directory not writable: {$dir}";
            }
        }

        // Check sessions
        $activeSessions = 0;
        try {
            $activeSessions = DB::table('sessions')->count();
        } catch (\Exception $e) {
            // Session table might not exist or be accessible
        }

        return [
            'status' => $status,
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'active_sessions' => $activeSessions,
            'issues' => $issues,
            'message' => $status === 'healthy' ? 'Application is healthy' : 'Application has issues',
        ];
    }

    protected function checkSecurity(): array
    {
        $this->line('🔒 Checking security...');

        $status = 'healthy';
        $issues = [];
        $recommendations = [];

        // Check APP_KEY
        if (empty(config('app.key'))) {
            $status = 'critical';
            $issues[] = 'Application key not set';
        }

        // Check HTTPS in production
        if (app()->environment('production') && ! request()->isSecure()) {
            $status = 'warning';
            $issues[] = 'HTTPS not enabled in production';
            $recommendations[] = 'Enable HTTPS for production environment';
        }

        // Check file permissions
        $sensitiveFiles = [
            base_path('.env'),
            storage_path(),
        ];

        foreach ($sensitiveFiles as $file) {
            if (file_exists($file)) {
                $permissions = substr(sprintf('%o', fileperms($file)), -4);
                if ($permissions > '0755') {
                    $status = 'warning';
                    $issues[] = "Overly permissive file permissions: {$file} ({$permissions})";
                    $recommendations[] = "Secure file permissions for {$file}";
                }
            }
        }

        return [
            'status' => $status,
            'app_key_set' => ! empty(config('app.key')),
            'https_enabled' => request()->isSecure(),
            'environment' => app()->environment(),
            'issues' => $issues,
            'recommendations' => $recommendations,
            'message' => $status === 'healthy' ? 'Security checks passed' : 'Security issues found',
        ];
    }

    protected function calculateOverallStatus(array $healthData): string
    {
        $criticalCount = 0;
        $warningCount = 0;

        foreach ($healthData as $check) {
            if ($check['status'] === 'critical') {
                $criticalCount++;
            } elseif ($check['status'] === 'warning') {
                $warningCount++;
            }
        }

        if ($criticalCount > 0) {
            return 'critical';
        } elseif ($warningCount > 0) {
            return 'warning';
        }

        return 'healthy';
    }

    protected function displayResults(array $healthData, string $overallStatus, string $format): void
    {
        $this->newLine();

        $statusIcon = match ($overallStatus) {
            'healthy' => '✅',
            'warning' => '⚠️',
            'critical' => '🚨',
            default => '❓'
        };

        $this->info("{$statusIcon} Overall System Status: ".strtoupper($overallStatus));
        $this->newLine();

        if ($format === 'json') {
            $this->line(json_encode([
                'overall_status' => $overallStatus,
                'checks' => $healthData,
                'timestamp' => now()->toISOString(),
            ], JSON_PRETTY_PRINT));

            return;
        }

        // Table format
        $headers = ['Component', 'Status', 'Message', 'Issues'];
        $rows = [];

        foreach ($healthData as $component => $data) {
            $statusIcon = match ($data['status']) {
                'healthy' => '✅',
                'warning' => '⚠️',
                'critical' => '🚨',
                default => '❓'
            };

            $rows[] = [
                ucfirst($component),
                $statusIcon.' '.ucfirst($data['status']),
                $data['message'],
                empty($data['issues']) ? 'None' : implode(', ', $data['issues']),
            ];
        }

        $this->table($headers, $rows);

        // Show recommendations if any
        $allRecommendations = [];
        foreach ($healthData as $data) {
            if (isset($data['recommendations'])) {
                $allRecommendations = array_merge($allRecommendations, $data['recommendations']);
            }
        }

        if (! empty($allRecommendations)) {
            $this->newLine();
            $this->info('💡 Recommendations:');
            foreach ($allRecommendations as $recommendation) {
                $this->line("  • {$recommendation}");
            }
        }
    }

    protected function exportResults(array $healthData, string $filename, string $format): void
    {
        $data = [
            'timestamp' => now()->toISOString(),
            'overall_status' => $this->calculateOverallStatus($healthData),
            'checks' => $healthData,
        ];

        switch ($format) {
            case 'json':
                file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
                break;

            case 'csv':
                $this->exportToCsv($healthData, $filename);
                break;

            default:
                $this->error("Unsupported export format: {$format}");

                return;
        }

        $this->info("📄 Health check results exported to: {$filename}");
    }

    protected function exportToCsv(array $healthData, string $filename): void
    {
        $file = fopen($filename, 'w');

        fputcsv($file, ['Component', 'Status', 'Message', 'Issues', 'Timestamp']);

        foreach ($healthData as $component => $data) {
            fputcsv($file, [
                $component,
                $data['status'],
                $data['message'],
                implode('; ', $data['issues'] ?? []),
                now()->toISOString(),
            ]);
        }

        fclose($file);
    }

    protected function getDatabaseStats(): array
    {
        try {
            $database = config('database.connections.'.config('database.default').'.database');
            if (! $database) {
                return [];
            }

            $stats = DB::select('
                SELECT 
                    COUNT(*) as table_count,
                    SUM(table_rows) as total_rows,
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                FROM information_schema.tables 
                WHERE table_schema = ?
            ', [$database]);

            return [
                'tables' => $stats[0]->table_count ?? 0,
                'total_rows' => $stats[0]->total_rows ?? 0,
                'size_mb' => $stats[0]->size_mb ?? 0,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function getMemoryLimit(): float
    {
        $limit = ini_get('memory_limit');
        if ($limit == -1) {
            return -1;
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
}
