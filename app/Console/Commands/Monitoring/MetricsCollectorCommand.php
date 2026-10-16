<?php

namespace App\Console\Commands\Monitoring;

use App\Services\Monitoring\AlertingService;
use App\Services\Monitoring\MetricsCollectionService;
use Illuminate\Console\Command;

class MetricsCollectorCommand extends Command
{
    protected $signature = 'monitoring:collect-metrics 
                            {--interval=60 : Collection interval in seconds}
                            {--duration=0 : Total duration in seconds (0 = infinite)}
                            {--store-detailed : Store detailed metrics}';

    protected $description = 'Collect system and application metrics for monitoring';

    protected MetricsCollectionService $metricsService;

    protected AlertingService $alertingService;

    public function __construct(
        MetricsCollectionService $metricsService,
        AlertingService $alertingService
    ) {
        parent::__construct();
        $this->metricsService = $metricsService;
        $this->alertingService = $alertingService;
    }

    public function handle()
    {
        $interval = (int) $this->option('interval');
        $duration = (int) $this->option('duration');
        $storeDetailed = $this->option('store-detailed');

        $this->info("🔍 Starting metrics collection (interval: {$interval}s)");

        if ($duration > 0) {
            $this->info("Duration: {$duration} seconds");
            $endTime = time() + $duration;
        } else {
            $this->info('Duration: Infinite (Ctrl+C to stop)');
            $endTime = null;
        }

        $startTime = time();
        $iterations = 0;

        while (true) {
            if ($endTime && time() >= $endTime) {
                break;
            }

            $iterationStart = microtime(true);

            try {
                // Collect metrics
                $this->info('📊 Collecting metrics...');
                $metrics = $this->metricsService->collectMetrics();

                // Display current metrics
                $this->displayMetrics($metrics);

                $iterations++;

                if ($storeDetailed) {
                    $this->storeDetailedMetrics($metrics, $iterations);
                }

            } catch (\Exception $e) {
                $this->error("Error collecting metrics: {$e->getMessage()}");
            }

            $iterationTime = microtime(true) - $iterationStart;
            $sleepTime = max(0, $interval - $iterationTime);

            if ($sleepTime > 0 && (! $endTime || time() + $sleepTime < $endTime)) {
                $this->info("💤 Sleeping for {$sleepTime} seconds...\n");
                sleep((int) $sleepTime);
            }
        }

        $totalTime = time() - $startTime;
        $this->info('✅ Metrics collection completed');
        $this->info("Total time: {$totalTime} seconds");
        $this->info("Total iterations: {$iterations}");

        return 0;
    }

    protected function displayMetrics(array $metrics): void
    {
        $this->newLine();
        $this->info('📈 Current Metrics - '.$metrics['timestamp']);

        // System metrics
        $this->line('💻 System:');
        $this->line("  Memory: {$metrics['system']['memory']['usage_mb']}MB / {$metrics['system']['memory']['limit_mb']}MB");
        $this->line("  Disk: {$metrics['system']['disk']['usage_percentage']}% used");
        $this->line("  Load: {$metrics['system']['cpu']['load_1min']}");

        // Performance metrics
        $this->line('⚡ Performance:');
        $this->line("  Response Time: {$metrics['performance']['avg_response_time_ms']}ms");
        $this->line("  Error Rate: {$metrics['performance']['error_rate_percentage']}%");
        $this->line("  Requests/min: {$metrics['performance']['requests_per_minute']}");

        // Queue metrics
        $this->line('📦 Queue:');
        $this->line("  Total Jobs: {$metrics['queue']['total_jobs']}");
        $this->line("  Failed Jobs: {$metrics['queue']['failed_jobs']}");
        $this->line("  Processing: {$metrics['queue']['processing_jobs']}");

        // Database metrics
        if ($metrics['database']['status'] === 'healthy') {
            $this->line('🗄️ Database:');
            $this->line("  Connection Time: {$metrics['database']['connection_time_ms']}ms");
            $this->line("  Active Connections: {$metrics['database']['active_connections']}");
            $this->line("  Database Size: {$metrics['database']['database_size_mb']}MB");
        } else {
            $this->error("🗄️ Database: ERROR - {$metrics['database']['error']}");
        }

        // Business metrics
        $this->line('📋 Business:');
        $this->line("  Active Shipments: {$metrics['business']['active_shipments']}");
        $this->line("  Completed Today: {$metrics['business']['completed_today']}");
        $this->line("  Pending Tasks: {$metrics['business']['pending_tasks']}");

        // Storage metrics
        if (isset($metrics['storage']['usage_percentage'])) {
            $this->line('💾 Storage:');
            $this->line("  Usage: {$metrics['storage']['usage_percentage']}%");
            $this->line("  Health Score: {$metrics['storage']['health_score']}/100");
        }

        // Show active alerts if any
        $activeAlerts = $this->alertingService->getActiveAlerts();
        if (! empty($activeAlerts)) {
            $this->newLine();
            $this->warn('🚨 Active Alerts: '.count($activeAlerts));
            foreach ($activeAlerts as $alert) {
                $level = strtoupper($alert['level']);
                $this->line("  [{$level}] {$alert['message']}");
            }
        } else {
            $this->line('✅ No active alerts');
        }

        $this->line(str_repeat('-', 60));
    }

    protected function storeDetailedMetrics(array $metrics, int $iteration): void
    {
        $filename = storage_path('logs/metrics-'.now()->format('Y-m-d').'.json');

        $entry = [
            'iteration' => $iteration,
            'timestamp' => $metrics['timestamp'],
            'metrics' => $metrics,
        ];

        file_put_contents($filename, json_encode($entry)."\n", FILE_APPEND | LOCK_EX);

        $this->line("💾 Detailed metrics stored to: {$filename}");
    }
}
