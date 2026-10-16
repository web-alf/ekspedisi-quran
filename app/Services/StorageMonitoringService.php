<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class StorageMonitoringService
{
    protected array $config;

    protected FileStorageService $fileStorageService;

    public function __construct(FileStorageService $fileStorageService)
    {
        $this->fileStorageService = $fileStorageService;
        $this->config = config('filesystems.storage_monitoring', [
            'enabled' => true,
            'check_interval' => 3600, // seconds
            'warning_threshold' => 80, // percentage
            'critical_threshold' => 95, // percentage
            'alert_email' => config('services.storage.alert_email'),
            'cache_stats_duration' => 1800, // 30 minutes
        ]);
    }

    /**
     * Monitor storage usage and trigger alerts if needed
     */
    public function monitorStorage(): array
    {
        if (! $this->config['enabled']) {
            return ['monitoring' => 'disabled'];
        }

        $cacheKey = 'storage_monitoring_stats';

        try {
            $stats = Cache::remember($cacheKey, $this->config['cache_stats_duration'], function () {
                return $this->gatherStorageStats();
            });
        } catch (\Exception $e) {
            // Fallback if cache is not available
            Log::warning('Cache not available for storage monitoring, using direct calculation', [
                'error' => $e->getMessage(),
            ]);
            $stats = $this->gatherStorageStats();
        }

        // Check for threshold violations
        $alerts = $this->checkThresholds($stats);

        // Log monitoring results
        $this->logMonitoringResults($stats, $alerts);

        // Send alerts if needed
        if (! empty($alerts)) {
            $this->sendAlerts($alerts, $stats);
        }

        return [
            'stats' => $stats,
            'alerts' => $alerts,
            'monitoring' => 'active',
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Gather comprehensive storage statistics
     */
    protected function gatherStorageStats(): array
    {
        $disk = Storage::disk('public');
        $storageStats = $this->fileStorageService->getStorageStats();

        // Get disk space information
        $diskSpace = $this->getDiskSpaceInfo();

        // Performance metrics
        $performanceStats = $this->gatherPerformanceStats();

        // Growth trends
        $growthStats = $this->calculateGrowthTrends();

        return array_merge($storageStats, [
            'disk_space' => $diskSpace,
            'performance' => $performanceStats,
            'growth' => $growthStats,
            'health_score' => $this->calculateHealthScore($storageStats, $diskSpace),
            'recommendations' => $this->generateRecommendations($storageStats, $diskSpace),
        ]);
    }

    /**
     * Get system disk space information
     */
    protected function getDiskSpaceInfo(): array
    {
        $path = Storage::disk('public')->path('');

        try {
            $totalSpace = disk_total_space($path);
            $freeSpace = disk_free_space($path);
            $usedSpace = $totalSpace - $freeSpace;

            return [
                'total_space' => $totalSpace,
                'free_space' => $freeSpace,
                'used_space' => $usedSpace,
                'usage_percentage' => round(($usedSpace / $totalSpace) * 100, 2),
                'total_space_formatted' => $this->formatFileSize($totalSpace),
                'free_space_formatted' => $this->formatFileSize($freeSpace),
                'used_space_formatted' => $this->formatFileSize($usedSpace),
            ];
        } catch (\Exception $e) {
            Log::warning('Could not retrieve disk space information', [
                'error' => $e->getMessage(),
                'path' => $path,
            ]);

            return [
                'total_space' => null,
                'free_space' => null,
                'used_space' => null,
                'usage_percentage' => null,
                'error' => 'Could not retrieve disk space information',
            ];
        }
    }

    /**
     * Gather performance-related statistics
     */
    protected function gatherPerformanceStats(): array
    {
        $start = microtime(true);

        // Test file operations performance
        $disk = Storage::disk('public');
        $testFile = 'performance_test_'.time().'.txt';
        $testContent = str_repeat('test', 1000); // 4KB test file

        try {
            // Write test
            $writeStart = microtime(true);
            $disk->put($testFile, $testContent);
            $writeTime = microtime(true) - $writeStart;

            // Read test
            $readStart = microtime(true);
            $disk->get($testFile);
            $readTime = microtime(true) - $readStart;

            // Delete test
            $deleteStart = microtime(true);
            $disk->delete($testFile);
            $deleteTime = microtime(true) - $deleteStart;

            $totalTime = microtime(true) - $start;

            return [
                'write_time_ms' => round($writeTime * 1000, 2),
                'read_time_ms' => round($readTime * 1000, 2),
                'delete_time_ms' => round($deleteTime * 1000, 2),
                'total_time_ms' => round($totalTime * 1000, 2),
                'status' => 'healthy',
            ];

        } catch (\Exception $e) {
            return [
                'write_time_ms' => null,
                'read_time_ms' => null,
                'delete_time_ms' => null,
                'total_time_ms' => null,
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate storage growth trends
     */
    protected function calculateGrowthTrends(): array
    {
        $cacheKey = 'storage_growth_history';
        try {
            $history = Cache::get($cacheKey, []);
        } catch (\Exception $e) {
            Log::warning('Cache not available for growth history', ['error' => $e->getMessage()]);
            $history = [];
        }

        // Add current data point
        $currentStats = $this->fileStorageService->getStorageStats();
        $history[] = [
            'timestamp' => now()->timestamp,
            'total_size' => $currentStats['total_size'],
            'file_count' => $currentStats['file_count'],
        ];

        // Keep only last 30 days
        $thirtyDaysAgo = now()->subDays(30)->timestamp;
        $history = array_filter($history, function ($point) use ($thirtyDaysAgo) {
            return $point['timestamp'] >= $thirtyDaysAgo;
        });

        // Store updated history
        try {
            Cache::put($cacheKey, $history, 86400 * 30); // 30 days
        } catch (\Exception $e) {
            Log::warning('Could not store growth history in cache', ['error' => $e->getMessage()]);
        }

        // Calculate trends
        $trends = $this->analyzeTrends($history);

        return [
            'data_points' => count($history),
            'daily_growth_size' => $trends['daily_growth_size'] ?? 0,
            'daily_growth_files' => $trends['daily_growth_files'] ?? 0,
            'projected_size_30_days' => $trends['projected_size_30_days'] ?? 0,
            'days_until_full' => $trends['days_until_full'] ?? null,
            'trend_direction' => $trends['trend_direction'] ?? 'stable',
        ];
    }

    /**
     * Analyze growth trends from historical data
     */
    protected function analyzeTrends(array $history): array
    {
        if (count($history) < 2) {
            return ['trend_direction' => 'insufficient_data'];
        }

        // Calculate daily averages
        $firstPoint = reset($history);
        $lastPoint = end($history);

        $daysDiff = ($lastPoint['timestamp'] - $firstPoint['timestamp']) / 86400;
        if ($daysDiff <= 0) {
            return ['trend_direction' => 'insufficient_time'];
        }

        $sizeGrowth = $lastPoint['total_size'] - $firstPoint['total_size'];
        $fileGrowth = $lastPoint['file_count'] - $firstPoint['file_count'];

        $dailyGrowthSize = $sizeGrowth / $daysDiff;
        $dailyGrowthFiles = $fileGrowth / $daysDiff;

        // Project future usage
        $projectedSize30Days = $lastPoint['total_size'] + ($dailyGrowthSize * 30);

        // Calculate days until storage full
        $maxStorageSize = $this->fileStorageService->getStorageStats()['max_storage_size'];
        $remainingSpace = $maxStorageSize - $lastPoint['total_size'];
        $daysUntilFull = $dailyGrowthSize > 0 ? ceil($remainingSpace / $dailyGrowthSize) : null;

        // Determine trend direction
        $trendDirection = 'stable';
        if ($dailyGrowthSize > 0) {
            $trendDirection = $dailyGrowthSize > 1000000 ? 'rapid_growth' : 'growing'; // 1MB threshold
        } elseif ($dailyGrowthSize < 0) {
            $trendDirection = 'declining';
        }

        return [
            'daily_growth_size' => round($dailyGrowthSize),
            'daily_growth_files' => round($dailyGrowthFiles, 1),
            'projected_size_30_days' => round($projectedSize30Days),
            'days_until_full' => $daysUntilFull,
            'trend_direction' => $trendDirection,
        ];
    }

    /**
     * Check storage thresholds and generate alerts
     */
    protected function checkThresholds(array $stats): array
    {
        $alerts = [];

        // Check application storage usage
        if (isset($stats['usage_percentage'])) {
            if ($stats['usage_percentage'] >= $this->config['critical_threshold']) {
                $alerts[] = [
                    'level' => 'critical',
                    'type' => 'storage_usage',
                    'message' => "Storage usage critical: {$stats['usage_percentage']}%",
                    'threshold' => $this->config['critical_threshold'],
                    'current' => $stats['usage_percentage'],
                ];
            } elseif ($stats['usage_percentage'] >= $this->config['warning_threshold']) {
                $alerts[] = [
                    'level' => 'warning',
                    'type' => 'storage_usage',
                    'message' => "Storage usage high: {$stats['usage_percentage']}%",
                    'threshold' => $this->config['warning_threshold'],
                    'current' => $stats['usage_percentage'],
                ];
            }
        }

        // Check system disk usage
        if (isset($stats['disk_space']['usage_percentage']) && $stats['disk_space']['usage_percentage']) {
            if ($stats['disk_space']['usage_percentage'] >= 90) {
                $alerts[] = [
                    'level' => 'critical',
                    'type' => 'disk_space',
                    'message' => "System disk usage critical: {$stats['disk_space']['usage_percentage']}%",
                    'threshold' => 90,
                    'current' => $stats['disk_space']['usage_percentage'],
                ];
            } elseif ($stats['disk_space']['usage_percentage'] >= 80) {
                $alerts[] = [
                    'level' => 'warning',
                    'type' => 'disk_space',
                    'message' => "System disk usage high: {$stats['disk_space']['usage_percentage']}%",
                    'threshold' => 80,
                    'current' => $stats['disk_space']['usage_percentage'],
                ];
            }
        }

        // Check performance issues
        if (isset($stats['performance']['total_time_ms']) && $stats['performance']['total_time_ms'] > 1000) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'performance',
                'message' => "Storage performance slow: {$stats['performance']['total_time_ms']}ms",
                'threshold' => 1000,
                'current' => $stats['performance']['total_time_ms'],
            ];
        }

        // Check growth trends
        if (isset($stats['growth']['days_until_full']) && $stats['growth']['days_until_full'] < 30) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'growth_projection',
                'message' => "Storage projected to be full in {$stats['growth']['days_until_full']} days",
                'threshold' => 30,
                'current' => $stats['growth']['days_until_full'],
            ];
        }

        return $alerts;
    }

    /**
     * Calculate overall storage health score
     */
    protected function calculateHealthScore(array $storageStats, array $diskSpace): int
    {
        $score = 100;

        // Deduct points for storage usage
        if (isset($storageStats['usage_percentage'])) {
            if ($storageStats['usage_percentage'] > 90) {
                $score -= 30;
            } elseif ($storageStats['usage_percentage'] > 80) {
                $score -= 20;
            } elseif ($storageStats['usage_percentage'] > 70) {
                $score -= 10;
            }
        }

        // Deduct points for disk usage
        if (isset($diskSpace['usage_percentage']) && $diskSpace['usage_percentage']) {
            if ($diskSpace['usage_percentage'] > 90) {
                $score -= 25;
            } elseif ($diskSpace['usage_percentage'] > 80) {
                $score -= 15;
            }
        }

        return max(0, $score);
    }

    /**
     * Generate storage optimization recommendations
     */
    protected function generateRecommendations(array $storageStats, array $diskSpace): array
    {
        $recommendations = [];

        if (isset($storageStats['usage_percentage']) && $storageStats['usage_percentage'] > 80) {
            $recommendations[] = [
                'type' => 'cleanup',
                'priority' => 'high',
                'message' => 'Consider running file cleanup to remove orphaned files',
                'action' => 'Run: php artisan storage:cleanup',
            ];

            $recommendations[] = [
                'type' => 'optimization',
                'priority' => 'medium',
                'message' => 'Optimize existing images to reduce storage usage',
                'action' => 'Run: php artisan images:optimize-existing',
            ];
        }

        if (isset($diskSpace['usage_percentage']) && $diskSpace['usage_percentage'] > 85) {
            $recommendations[] = [
                'type' => 'disk_space',
                'priority' => 'critical',
                'message' => 'System disk space critically low. Consider expanding storage or moving files to external storage.',
                'action' => 'Contact system administrator',
            ];
        }

        if (count($recommendations) === 0) {
            $recommendations[] = [
                'type' => 'maintenance',
                'priority' => 'low',
                'message' => 'Storage is healthy. Regular maintenance recommended.',
                'action' => 'Schedule weekly cleanup tasks',
            ];
        }

        return $recommendations;
    }

    /**
     * Log monitoring results
     */
    protected function logMonitoringResults(array $stats, array $alerts): void
    {
        $logData = [
            'storage_usage_percentage' => $stats['usage_percentage'] ?? null,
            'total_files' => $stats['file_count'] ?? null,
            'total_size_formatted' => $stats['total_size_formatted'] ?? null,
            'alerts_count' => count($alerts),
            'health_score' => $stats['health_score'] ?? null,
        ];

        if (empty($alerts)) {
            Log::info('Storage monitoring: All systems healthy', $logData);
        } else {
            $criticalAlerts = array_filter($alerts, fn ($alert) => $alert['level'] === 'critical');
            if (! empty($criticalAlerts)) {
                Log::critical('Storage monitoring: Critical alerts detected', array_merge($logData, ['alerts' => $alerts]));
            } else {
                Log::warning('Storage monitoring: Warnings detected', array_merge($logData, ['alerts' => $alerts]));
            }
        }
    }

    /**
     * Send alerts via configured channels
     */
    protected function sendAlerts(array $alerts, array $stats): void
    {
        $criticalAlerts = array_filter($alerts, fn ($alert) => $alert['level'] === 'critical');

        if (! empty($criticalAlerts) && $this->config['alert_email']) {
            try {
                $this->sendEmailAlert($criticalAlerts, $stats);
            } catch (\Exception $e) {
                Log::error('Failed to send storage alert email', [
                    'error' => $e->getMessage(),
                    'alerts' => $criticalAlerts,
                ]);
            }
        }

        // Store alerts in cache for dashboard display
        try {
            Cache::put('storage_active_alerts', $alerts, 3600);
        } catch (\Exception $e) {
            Log::warning('Could not store alerts in cache', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send email alert for critical storage issues
     */
    protected function sendEmailAlert(array $alerts, array $stats): void
    {
        $subject = 'Critical Storage Alert - '.config('app.name');
        $body = "Critical storage alerts detected:\n\n";

        foreach ($alerts as $alert) {
            $body .= "- {$alert['message']}\n";
        }

        $body .= "\nStorage Statistics:\n";
        $body .= '- Total Files: '.($stats['file_count'] ?? 'N/A')."\n";
        $body .= '- Total Size: '.($stats['total_size_formatted'] ?? 'N/A')."\n";
        $body .= '- Usage: '.($stats['usage_percentage'] ?? 'N/A')."%\n";
        $body .= '- Health Score: '.($stats['health_score'] ?? 'N/A')."/100\n";

        $body .= "\nRecommended Actions:\n";
        foreach ($stats['recommendations'] ?? [] as $rec) {
            $body .= "- {$rec['message']}\n";
            $body .= "  Action: {$rec['action']}\n\n";
        }

        // Simple mail sending (you might want to use a proper mail template)
        mail($this->config['alert_email'], $subject, $body);
    }

    /**
     * Get current active alerts
     */
    public function getActiveAlerts(): array
    {
        try {
            return Cache::get('storage_active_alerts', []);
        } catch (\Exception $e) {
            Log::warning('Could not retrieve alerts from cache', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Clear active alerts
     */
    public function clearActiveAlerts(): void
    {
        try {
            Cache::forget('storage_active_alerts');
        } catch (\Exception $e) {
            Log::warning('Could not clear alerts from cache', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Format file size to human readable format
     */
    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Generate storage monitoring report
     */
    public function generateReport(): array
    {
        try {
            $stats = Cache::get('storage_monitoring_stats');
        } catch (\Exception $e) {
            $stats = null;
        }

        if (! $stats) {
            $stats = $this->gatherStorageStats();
            try {
                Cache::put('storage_monitoring_stats', $stats, $this->config['cache_stats_duration']);
            } catch (\Exception $e) {
                Log::warning('Could not cache monitoring stats', ['error' => $e->getMessage()]);
            }
        }

        return [
            'report_generated_at' => now()->toISOString(),
            'statistics' => $stats,
            'active_alerts' => $this->getActiveAlerts(),
            'recommendations' => $stats['recommendations'] ?? [],
            'summary' => [
                'health_status' => $this->getHealthStatus($stats['health_score'] ?? 0),
                'storage_usage' => $stats['usage_percentage'] ?? 0,
                'total_files' => $stats['file_count'] ?? 0,
                'alerts_count' => count($this->getActiveAlerts()),
            ],
        ];
    }

    /**
     * Get health status text based on score
     */
    protected function getHealthStatus(int $score): string
    {
        if ($score >= 90) {
            return 'Excellent';
        }
        if ($score >= 80) {
            return 'Good';
        }
        if ($score >= 70) {
            return 'Fair';
        }
        if ($score >= 60) {
            return 'Poor';
        }

        return 'Critical';
    }
}
