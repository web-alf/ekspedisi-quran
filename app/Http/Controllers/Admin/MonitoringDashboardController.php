<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\AlertingService;
use App\Services\Monitoring\MetricsCollectionService;
use App\Services\Monitoring\PerformanceBenchmarkService;
use App\Services\PerformanceMonitoringService;
use App\Services\StorageMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MonitoringDashboardController extends Controller
{
    protected MetricsCollectionService $metricsService;

    protected AlertingService $alertingService;

    protected PerformanceBenchmarkService $benchmarkService;

    protected PerformanceMonitoringService $performanceService;

    protected StorageMonitoringService $storageService;

    public function __construct(
        MetricsCollectionService $metricsService,
        AlertingService $alertingService,
        PerformanceBenchmarkService $benchmarkService,
        PerformanceMonitoringService $performanceService,
        StorageMonitoringService $storageService
    ) {
        $this->metricsService = $metricsService;
        $this->alertingService = $alertingService;
        $this->benchmarkService = $benchmarkService;
        $this->performanceService = $performanceService;
        $this->storageService = $storageService;
    }

    /**
     * Main monitoring dashboard
     */
    public function index()
    {
        $metrics = $this->metricsService->collectMetrics();
        $activeAlerts = $this->alertingService->getActiveAlerts();
        $performanceData = $this->performanceService->getDashboardMetrics();
        $storageReport = $this->storageService->generateReport();

        return Inertia::render('Admin/Monitoring/Dashboard', [
            'metrics' => $metrics,
            'alerts' => $activeAlerts,
            'performance' => $performanceData,
            'storage' => $storageReport,
            'overview' => $this->getOverviewStats($metrics),
            'health_status' => $this->getHealthStatus($metrics, $activeAlerts),
        ]);
    }

    /**
     * Real-time metrics API endpoint
     */
    public function realTimeMetrics()
    {
        $metrics = $this->metricsService->collectMetrics();

        return response()->json([
            'timestamp' => now()->toISOString(),
            'system' => [
                'memory_usage_mb' => $metrics['system']['memory']['usage_mb'],
                'cpu_load' => $metrics['system']['cpu']['load_1min'],
                'disk_usage_percent' => $metrics['system']['disk']['usage_percentage'],
            ],
            'performance' => [
                'response_time_ms' => $metrics['performance']['avg_response_time_ms'],
                'error_rate' => $metrics['performance']['error_rate_percentage'],
                'requests_per_minute' => $metrics['performance']['requests_per_minute'],
            ],
            'queue' => [
                'total_jobs' => $metrics['queue']['total_jobs'],
                'failed_jobs' => $metrics['queue']['failed_jobs'],
                'processing_jobs' => $metrics['queue']['processing_jobs'],
            ],
            'business' => [
                'active_shipments' => $metrics['business']['active_shipments'],
                'completed_today' => $metrics['business']['completed_today'],
                'pending_tasks' => $metrics['business']['pending_tasks'],
            ],
            'alerts_count' => count($this->alertingService->getActiveAlerts()),
        ]);
    }

    /**
     * Historical metrics endpoint
     */
    public function historicalMetrics(Request $request)
    {
        $period = $request->get('period', '24h');
        $metrics = $this->metricsService->getHistoricalMetrics($period);

        return response()->json([
            'period' => $period,
            'data' => $metrics,
            'summary' => $this->summarizeHistoricalData($metrics),
        ]);
    }

    /**
     * Performance benchmarks
     */
    public function benchmarks()
    {
        $report = $this->benchmarkService->generateReport();

        return Inertia::render('Admin/Monitoring/Benchmarks', [
            'report' => $report,
            'history' => $this->benchmarkService->getBenchmarkHistory(48),
        ]);
    }

    /**
     * Run performance benchmarks
     */
    public function runBenchmarks()
    {
        $results = $this->benchmarkService->runBenchmarks();

        return response()->json([
            'success' => true,
            'results' => $results,
            'message' => 'Performance benchmarks completed successfully',
        ]);
    }

    /**
     * Set performance baselines
     */
    public function setBaselines()
    {
        $benchmarks = $this->benchmarkService->runBenchmarks();
        $this->benchmarkService->setBaselines($benchmarks['benchmarks']);

        return response()->json([
            'success' => true,
            'message' => 'Performance baselines updated successfully',
            'baselines' => $benchmarks['benchmarks'],
        ]);
    }

    /**
     * Alerts management
     */
    public function alerts()
    {
        $activeAlerts = $this->alertingService->getActiveAlerts();
        $alertHistory = $this->alertingService->getAlertHistory(7);
        $alertStats = $this->alertingService->getAlertStatistics(7);

        return Inertia::render('Admin/Monitoring/Alerts', [
            'active_alerts' => $activeAlerts,
            'alert_history' => $alertHistory,
            'alert_statistics' => $alertStats,
        ]);
    }

    /**
     * Clear active alerts
     */
    public function clearAlerts()
    {
        $this->alertingService->clearActiveAlerts();

        return response()->json([
            'success' => true,
            'message' => 'Active alerts cleared successfully',
        ]);
    }

    /**
     * System health check
     */
    public function healthCheck()
    {
        $metrics = $this->metricsService->collectMetrics();
        $alerts = $this->alertingService->getActiveAlerts();

        $health = [
            'overall_status' => $this->calculateOverallHealth($metrics, $alerts),
            'components' => [
                'database' => $this->checkComponentHealth($metrics['database']),
                'cache' => $this->checkComponentHealth($metrics['cache']),
                'queue' => $this->checkQueueHealth($metrics['queue']),
                'storage' => $this->checkStorageHealth($metrics['storage']),
                'system' => $this->checkSystemHealth($metrics['system']),
            ],
            'alerts_count' => count($alerts),
            'timestamp' => now()->toISOString(),
        ];

        return response()->json($health);
    }

    /**
     * Performance reports
     */
    public function reports()
    {
        return Inertia::render('Admin/Monitoring/Reports', [
            'available_reports' => $this->getAvailableReports(),
        ]);
    }

    /**
     * Generate performance report
     */
    public function generateReport(Request $request)
    {
        $type = $request->get('type', 'comprehensive');
        $period = $request->get('period', '24h');
        $format = $request->get('format', 'json');

        $report = $this->buildReport($type, $period);

        if ($format === 'csv') {
            return $this->exportReportToCsv($report, $type);
        }

        return response()->json($report);
    }

    /**
     * Optimization recommendations
     */
    public function recommendations()
    {
        $metrics = $this->metricsService->collectMetrics();
        $benchmarkReport = $this->benchmarkService->generateReport();
        $storageReport = $this->storageService->generateReport();

        $recommendations = array_merge(
            $this->getSystemRecommendations($metrics),
            $benchmarkReport['recommendations'] ?? [],
            $storageReport['recommendations'] ?? []
        );

        return Inertia::render('Admin/Monitoring/Recommendations', [
            'recommendations' => $recommendations,
            'metrics_summary' => $this->getMetricsSummary($metrics),
        ]);
    }

    /**
     * Force metrics collection
     */
    public function collectMetrics()
    {
        $metrics = $this->metricsService->collectMetrics();

        return response()->json([
            'success' => true,
            'metrics' => $metrics,
            'message' => 'Metrics collected successfully',
        ]);
    }

    // Helper methods

    protected function getOverviewStats(array $metrics): array
    {
        return [
            'system_health_score' => $this->calculateSystemHealthScore($metrics),
            'performance_score' => $this->calculatePerformanceScore($metrics),
            'uptime_hours' => $metrics['application']['uptime'] / 3600,
            'total_requests_today' => $metrics['performance']['requests_per_minute'] * 60 * 24,
            'avg_response_time' => $metrics['performance']['avg_response_time_ms'],
            'error_rate' => $metrics['performance']['error_rate_percentage'],
        ];
    }

    protected function getHealthStatus(array $metrics, array $alerts): string
    {
        $criticalAlerts = array_filter($alerts, fn ($alert) => $alert['level'] === 'critical');

        if (! empty($criticalAlerts)) {
            return 'critical';
        }

        $warningAlerts = array_filter($alerts, fn ($alert) => $alert['level'] === 'warning');
        if (! empty($warningAlerts)) {
            return 'warning';
        }

        // Check key metrics
        if ($metrics['system']['memory']['usage_mb'] > 512 ||
            $metrics['performance']['error_rate_percentage'] > 5 ||
            $metrics['performance']['avg_response_time_ms'] > 2000) {
            return 'warning';
        }

        return 'healthy';
    }

    protected function summarizeHistoricalData(array $metrics): array
    {
        if (empty($metrics)) {
            return ['status' => 'no_data'];
        }

        // Extract key metrics for analysis
        $responseTimeData = [];
        $memoryData = [];
        $errorRateData = [];

        foreach ($metrics as $entry) {
            if (isset($entry['data']['response_time'])) {
                $responseTimeData[] = $entry['data']['response_time']['avg'];
            }
            if (isset($entry['data']['memory_usage'])) {
                $memoryData[] = $entry['data']['memory_usage']['avg'];
            }
            if (isset($entry['data']['error_rate'])) {
                $errorRateData[] = $entry['data']['error_rate']['avg'];
            }
        }

        return [
            'data_points' => count($metrics),
            'response_time' => [
                'avg' => ! empty($responseTimeData) ? round(array_sum($responseTimeData) / count($responseTimeData), 2) : 0,
                'min' => ! empty($responseTimeData) ? min($responseTimeData) : 0,
                'max' => ! empty($responseTimeData) ? max($responseTimeData) : 0,
            ],
            'memory_usage' => [
                'avg' => ! empty($memoryData) ? round(array_sum($memoryData) / count($memoryData), 2) : 0,
                'min' => ! empty($memoryData) ? min($memoryData) : 0,
                'max' => ! empty($memoryData) ? max($memoryData) : 0,
            ],
            'error_rate' => [
                'avg' => ! empty($errorRateData) ? round(array_sum($errorRateData) / count($errorRateData), 2) : 0,
                'min' => ! empty($errorRateData) ? min($errorRateData) : 0,
                'max' => ! empty($errorRateData) ? max($errorRateData) : 0,
            ],
        ];
    }

    protected function calculateOverallHealth(array $metrics, array $alerts): string
    {
        $score = 100;

        // Deduct points for alerts
        foreach ($alerts as $alert) {
            $score -= match ($alert['level']) {
                'critical' => 25,
                'warning' => 10,
                'info' => 2,
                default => 0
            };
        }

        // Deduct points for poor metrics
        if ($metrics['performance']['error_rate_percentage'] > 5) {
            $score -= 20;
        }
        if ($metrics['performance']['avg_response_time_ms'] > 2000) {
            $score -= 15;
        }
        if ($metrics['system']['memory']['usage_mb'] > 512) {
            $score -= 10;
        }

        return match (true) {
            $score >= 90 => 'excellent',
            $score >= 70 => 'good',
            $score >= 50 => 'fair',
            $score >= 30 => 'poor',
            default => 'critical'
        };
    }

    protected function checkComponentHealth(array $componentData): array
    {
        if (isset($componentData['status'])) {
            return [
                'status' => $componentData['status'],
                'message' => $componentData['message'] ?? 'Component status unknown',
            ];
        }

        return [
            'status' => 'unknown',
            'message' => 'Unable to determine component health',
        ];
    }

    protected function checkQueueHealth(array $queueData): array
    {
        $totalJobs = $queueData['total_jobs'] ?? 0;
        $failedJobs = $queueData['failed_jobs'] ?? 0;

        if ($failedJobs > 50) {
            return ['status' => 'critical', 'message' => 'High number of failed jobs'];
        }
        if ($totalJobs > 1000) {
            return ['status' => 'warning', 'message' => 'Large queue backlog'];
        }
        if ($failedJobs > 10) {
            return ['status' => 'warning', 'message' => 'Some failed jobs detected'];
        }

        return ['status' => 'healthy', 'message' => 'Queue system is operating normally'];
    }

    protected function checkStorageHealth(array $storageData): array
    {
        $usage = $storageData['usage_percentage'] ?? 0;
        $healthScore = $storageData['health_score'] ?? 100;

        if ($usage > 95 || $healthScore < 50) {
            return ['status' => 'critical', 'message' => 'Storage needs immediate attention'];
        }
        if ($usage > 80 || $healthScore < 80) {
            return ['status' => 'warning', 'message' => 'Storage should be monitored'];
        }

        return ['status' => 'healthy', 'message' => 'Storage is healthy'];
    }

    protected function checkSystemHealth(array $systemData): array
    {
        $memoryUsage = $systemData['memory']['usage_mb'] ?? 0;
        $diskUsage = $systemData['disk']['usage_percentage'] ?? 0;
        $cpuLoad = $systemData['cpu']['load_1min'] ?? 0;

        if ($memoryUsage > 1024 || $diskUsage > 95 || $cpuLoad > 10) {
            return ['status' => 'critical', 'message' => 'System resources critically low'];
        }
        if ($memoryUsage > 512 || $diskUsage > 80 || $cpuLoad > 5) {
            return ['status' => 'warning', 'message' => 'System resources under pressure'];
        }

        return ['status' => 'healthy', 'message' => 'System resources are adequate'];
    }

    protected function calculateSystemHealthScore(array $metrics): int
    {
        $score = 100;

        // Memory score
        $memoryUsage = $metrics['system']['memory']['usage_mb'] ?? 0;
        if ($memoryUsage > 512) {
            $score -= 20;
        }

        // Disk score
        $diskUsage = $metrics['system']['disk']['usage_percentage'] ?? 0;
        if ($diskUsage > 80) {
            $score -= 15;
        }

        // Performance score
        $errorRate = $metrics['performance']['error_rate_percentage'] ?? 0;
        if ($errorRate > 5) {
            $score -= 25;
        }

        return max(0, $score);
    }

    protected function calculatePerformanceScore(array $metrics): int
    {
        $score = 100;

        $responseTime = $metrics['performance']['avg_response_time_ms'] ?? 0;
        if ($responseTime > 2000) {
            $score -= 30;
        } elseif ($responseTime > 1000) {
            $score -= 15;
        }

        $errorRate = $metrics['performance']['error_rate_percentage'] ?? 0;
        if ($errorRate > 5) {
            $score -= 30;
        } elseif ($errorRate > 2) {
            $score -= 15;
        }

        return max(0, $score);
    }

    protected function getAvailableReports(): array
    {
        return [
            [
                'id' => 'comprehensive',
                'name' => 'Comprehensive Performance Report',
                'description' => 'Complete system performance analysis including all metrics and benchmarks',
            ],
            [
                'id' => 'performance',
                'name' => 'Performance Summary',
                'description' => 'Focus on response times, throughput, and performance metrics',
            ],
            [
                'id' => 'system',
                'name' => 'System Health Report',
                'description' => 'System resources, storage, and infrastructure health',
            ],
            [
                'id' => 'business',
                'name' => 'Business Metrics Report',
                'description' => 'Application-specific metrics and business KPIs',
            ],
        ];
    }

    protected function buildReport(string $type, string $period): array
    {
        $metrics = $this->metricsService->collectMetrics();
        $historicalData = $this->metricsService->getHistoricalMetrics($period);

        $baseReport = [
            'type' => $type,
            'period' => $period,
            'generated_at' => now()->toISOString(),
            'summary' => $this->summarizeHistoricalData($historicalData),
        ];

        switch ($type) {
            case 'comprehensive':
                return array_merge($baseReport, [
                    'current_metrics' => $metrics,
                    'historical_data' => $historicalData,
                    'benchmarks' => $this->benchmarkService->generateReport(),
                    'storage_report' => $this->storageService->generateReport(),
                    'alerts' => $this->alertingService->getAlertHistory(7),
                ]);

            case 'performance':
                return array_merge($baseReport, [
                    'performance_metrics' => $metrics['performance'],
                    'system_metrics' => $metrics['system'],
                    'benchmarks' => $this->benchmarkService->runBenchmarks(),
                ]);

            case 'system':
                return array_merge($baseReport, [
                    'system_metrics' => $metrics['system'],
                    'storage_report' => $this->storageService->generateReport(),
                    'health_checks' => $this->getHealthStatus($metrics, []),
                ]);

            case 'business':
                return array_merge($baseReport, [
                    'business_metrics' => $metrics['business'],
                    'application_metrics' => $metrics['application'],
                    'queue_metrics' => $metrics['queue'],
                ]);

            default:
                return $baseReport;
        }
    }

    protected function getSystemRecommendations(array $metrics): array
    {
        $recommendations = [];

        // High memory usage
        if ($metrics['system']['memory']['usage_mb'] > 512) {
            $recommendations[] = [
                'category' => 'system',
                'priority' => 'high',
                'title' => 'High Memory Usage',
                'description' => 'System memory usage is high and may impact performance',
                'actions' => [
                    'Review memory-intensive processes',
                    'Optimize application memory usage',
                    'Consider increasing server memory',
                ],
            ];
        }

        // High error rate
        if ($metrics['performance']['error_rate_percentage'] > 5) {
            $recommendations[] = [
                'category' => 'performance',
                'priority' => 'critical',
                'title' => 'High Error Rate',
                'description' => 'Application error rate is above acceptable threshold',
                'actions' => [
                    'Review error logs immediately',
                    'Check recent deployments',
                    'Monitor external service dependencies',
                ],
            ];
        }

        return $recommendations;
    }

    protected function getMetricsSummary(array $metrics): array
    {
        return [
            'response_time' => $metrics['performance']['avg_response_time_ms'],
            'memory_usage' => $metrics['system']['memory']['usage_mb'],
            'error_rate' => $metrics['performance']['error_rate_percentage'],
            'queue_size' => $metrics['queue']['total_jobs'],
            'disk_usage' => $metrics['system']['disk']['usage_percentage'],
        ];
    }

    protected function exportReportToCsv(array $report, string $type): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = "monitoring_report_{$type}_".now()->format('Y-m-d_H-i-s').'.csv';

        return response()->stream(function () use ($report) {
            $handle = fopen('php://output', 'w');

            // Add headers
            fputcsv($handle, ['Metric', 'Category', 'Value', 'Timestamp']);

            // Add current metrics
            if (isset($report['current_metrics'])) {
                $this->addMetricsToCSV($handle, $report['current_metrics'], $report['generated_at']);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function addMetricsToCSV($handle, array $metrics, string $timestamp): void
    {
        foreach ($metrics as $category => $categoryData) {
            if (is_array($categoryData)) {
                foreach ($categoryData as $metric => $value) {
                    if (is_scalar($value)) {
                        fputcsv($handle, [$metric, $category, $value, $timestamp]);
                    }
                }
            }
        }
    }
}
