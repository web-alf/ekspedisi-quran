<?php

namespace App\Console\Commands\Storage;

use App\Services\FileStorageService;
use App\Services\StorageMonitoringService;
use Illuminate\Console\Command;

class MonitorStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:monitor 
                            {--report : Generate detailed report}
                            {--alerts : Show active alerts only}
                            {--json : Output as JSON}
                            {--email : Send report via email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor storage usage and performance';

    protected StorageMonitoringService $monitoringService;

    protected FileStorageService $fileStorageService;

    public function __construct(StorageMonitoringService $monitoringService, FileStorageService $fileStorageService)
    {
        parent::__construct();
        $this->monitoringService = $monitoringService;
        $this->fileStorageService = $fileStorageService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $showReport = $this->option('report');
        $alertsOnly = $this->option('alerts');
        $jsonOutput = $this->option('json');
        $sendEmail = $this->option('email');

        if ($alertsOnly) {
            $this->showActiveAlerts($jsonOutput);

            return self::SUCCESS;
        }

        // Run monitoring
        $monitoringResult = $this->monitoringService->monitorStorage();

        if ($showReport) {
            $report = $this->monitoringService->generateReport();
            $this->displayDetailedReport($report, $jsonOutput);
        } else {
            $this->displayBasicStats($monitoringResult, $jsonOutput);
        }

        if ($sendEmail) {
            $this->sendEmailReport();
        }

        return self::SUCCESS;
    }

    protected function showActiveAlerts(bool $jsonOutput = false): void
    {
        $alerts = $this->monitoringService->getActiveAlerts();

        if ($jsonOutput) {
            $this->line(json_encode($alerts, JSON_PRETTY_PRINT));

            return;
        }

        if (empty($alerts)) {
            $this->info('✅ No active storage alerts');

            return;
        }

        $this->warn('⚠️  Active Storage Alerts:');

        foreach ($alerts as $alert) {
            $icon = $alert['level'] === 'critical' ? '🚨' : '⚠️';
            $this->line("{$icon} [{$alert['level']}] {$alert['message']}");
        }
    }

    protected function displayBasicStats(array $monitoringResult, bool $jsonOutput = false): void
    {
        if ($jsonOutput) {
            $this->line(json_encode($monitoringResult, JSON_PRETTY_PRINT));

            return;
        }

        $stats = $monitoringResult['stats'];
        $alerts = $monitoringResult['alerts'];

        $this->info('📊 Storage Monitoring Report');
        $this->info('Generated: '.now()->format('Y-m-d H:i:s'));

        // Storage Overview
        $this->info("\n🗂️  Storage Overview:");
        $this->table(['Metric', 'Value'], [
            ['Total Files', number_format($stats['file_count'] ?? 0)],
            ['Total Size', $stats['total_size_formatted'] ?? 'N/A'],
            ['Storage Usage', ($stats['usage_percentage'] ?? 0).'%'],
            ['Available Space', $stats['available_space_formatted'] ?? 'N/A'],
            ['Health Score', ($stats['health_score'] ?? 0).'/100'],
        ]);

        // System Disk Space
        if (isset($stats['disk_space'])) {
            $this->info("\n💽 System Disk Space:");
            $diskSpace = $stats['disk_space'];
            $this->table(['Metric', 'Value'], [
                ['Total Space', $diskSpace['total_space_formatted'] ?? 'N/A'],
                ['Used Space', $diskSpace['used_space_formatted'] ?? 'N/A'],
                ['Free Space', $diskSpace['free_space_formatted'] ?? 'N/A'],
                ['Usage', ($diskSpace['usage_percentage'] ?? 0).'%'],
            ]);
        }

        // Performance
        if (isset($stats['performance'])) {
            $this->info("\n⚡ Performance Metrics:");
            $performance = $stats['performance'];
            $this->table(['Operation', 'Time (ms)', 'Status'], [
                ['Write Test', $performance['write_time_ms'] ?? 'N/A', $performance['status'] ?? 'Unknown'],
                ['Read Test', $performance['read_time_ms'] ?? 'N/A', $performance['status'] ?? 'Unknown'],
                ['Delete Test', $performance['delete_time_ms'] ?? 'N/A', $performance['status'] ?? 'Unknown'],
                ['Total Time', $performance['total_time_ms'] ?? 'N/A', $performance['status'] ?? 'Unknown'],
            ]);
        }

        // Growth Trends
        if (isset($stats['growth'])) {
            $this->info("\n📈 Growth Trends:");
            $growth = $stats['growth'];
            $this->table(['Metric', 'Value'], [
                ['Daily Growth (Size)', $this->formatFileSize($growth['daily_growth_size'] ?? 0)],
                ['Daily Growth (Files)', number_format($growth['daily_growth_files'] ?? 0, 1)],
                ['Projected Size (30 days)', $this->formatFileSize($growth['projected_size_30_days'] ?? 0)],
                ['Days Until Full', $growth['days_until_full'] ?? 'N/A'],
                ['Trend Direction', $growth['trend_direction'] ?? 'Unknown'],
            ]);
        }

        // Directory Breakdown
        if (isset($stats['directory_stats']) && ! empty($stats['directory_stats'])) {
            $this->info("\n📁 Top Directories by Size:");
            $directoryData = [];
            $count = 0;
            foreach ($stats['directory_stats'] as $dir => $dirStats) {
                if ($count >= 10) {
                    break;
                } // Show top 10
                $directoryData[] = [
                    $dir,
                    number_format($dirStats['count']),
                    $this->formatFileSize($dirStats['size']),
                ];
                $count++;
            }
            $this->table(['Directory', 'Files', 'Size'], $directoryData);
        }

        // Alerts
        if (! empty($alerts)) {
            $this->warn("\n⚠️  Active Alerts:");
            foreach ($alerts as $alert) {
                $icon = $alert['level'] === 'critical' ? '🚨' : '⚠️';
                $this->line("{$icon} [{$alert['level']}] {$alert['message']}");
            }
        } else {
            $this->info("\n✅ No active alerts");
        }

        // Recommendations
        if (isset($stats['recommendations']) && ! empty($stats['recommendations'])) {
            $this->info("\n💡 Recommendations:");
            foreach ($stats['recommendations'] as $rec) {
                $priority = match ($rec['priority']) {
                    'critical' => '🚨',
                    'high' => '🔴',
                    'medium' => '🟡',
                    default => '🔵'
                };
                $this->line("{$priority} [{$rec['priority']}] {$rec['message']}");
                $this->line("   Action: {$rec['action']}");
            }
        }
    }

    protected function displayDetailedReport(array $report, bool $jsonOutput = false): void
    {
        if ($jsonOutput) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));

            return;
        }

        $this->displayBasicStats([
            'stats' => $report['statistics'],
            'alerts' => $report['active_alerts'],
        ], false);

        // Additional detailed information
        $this->info("\n📋 Report Summary:");
        $summary = $report['summary'];
        $this->table(['Metric', 'Value'], [
            ['Health Status', $summary['health_status']],
            ['Storage Usage', $summary['storage_usage'].'%'],
            ['Total Files', number_format($summary['total_files'])],
            ['Active Alerts', $summary['alerts_count']],
            ['Report Generated', $report['report_generated_at']],
        ]);
    }

    protected function sendEmailReport(): void
    {
        try {
            $report = $this->monitoringService->generateReport();

            // Simple email implementation
            $alertEmail = config('filesystems.storage_monitoring.alert_email');

            if (! $alertEmail) {
                $this->error('No alert email configured. Set STORAGE_ALERT_EMAIL in .env');

                return;
            }

            $subject = 'Storage Monitoring Report - '.config('app.name');
            $body = $this->generateEmailBody($report);

            if (mail($alertEmail, $subject, $body)) {
                $this->info('✅ Report sent to: '.$alertEmail);
            } else {
                $this->error('❌ Failed to send email report');
            }

        } catch (\Exception $e) {
            $this->error('❌ Error sending email: '.$e->getMessage());
        }
    }

    protected function generateEmailBody(array $report): string
    {
        $summary = $report['summary'];
        $stats = $report['statistics'];

        $body = "Storage Monitoring Report\n";
        $body .= "========================\n\n";
        $body .= 'Generated: '.$report['report_generated_at']."\n\n";

        $body .= "Summary:\n";
        $body .= '- Health Status: '.$summary['health_status']."\n";
        $body .= '- Storage Usage: '.$summary['storage_usage']."%\n";
        $body .= '- Total Files: '.number_format($summary['total_files'])."\n";
        $body .= '- Active Alerts: '.$summary['alerts_count']."\n\n";

        if (! empty($report['active_alerts'])) {
            $body .= "Active Alerts:\n";
            foreach ($report['active_alerts'] as $alert) {
                $body .= "- [{$alert['level']}] {$alert['message']}\n";
            }
            $body .= "\n";
        }

        if (! empty($report['recommendations'])) {
            $body .= "Recommendations:\n";
            foreach ($report['recommendations'] as $rec) {
                $body .= "- [{$rec['priority']}] {$rec['message']}\n";
                $body .= "  Action: {$rec['action']}\n";
            }
        }

        return $body;
    }

    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
