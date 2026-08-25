<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class QueueHealthCheckCommand extends Command
{
    protected $signature = 'queue:health-check 
                            {--detailed : Show detailed queue statistics}
                            {--alert-threshold=100 : Alert when queue size exceeds threshold}
                            {--failed-threshold=10 : Alert when failed jobs exceed threshold}';

    protected $description = 'Check the health of Redis queue system and report issues';

    public function handle()
    {
        $this->info('🔍 Starting Queue Health Check...');

        $issues = [];
        $stats = [];

        // Check Redis connection
        $redisStatus = $this->checkRedisConnection();
        if (! $redisStatus['healthy']) {
            $issues[] = $redisStatus['message'];
        }
        $stats['redis'] = $redisStatus;

        // Check queue sizes
        $queueStats = $this->checkQueueSizes();
        $stats['queues'] = $queueStats;

        // Check for alerts
        foreach ($queueStats as $queue => $info) {
            if ($info['size'] > $this->option('alert-threshold')) {
                $issues[] = "⚠️ Queue '{$queue}' has {$info['size']} jobs (threshold: {$this->option('alert-threshold')})";
            }
        }

        // Check failed jobs
        $failedStats = $this->checkFailedJobs();
        $stats['failed_jobs'] = $failedStats;

        if ($failedStats['count'] > $this->option('failed-threshold')) {
            $issues[] = "❌ {$failedStats['count']} failed jobs (threshold: {$this->option('failed-threshold')})";
        }

        // Check job processing rates
        $processStats = $this->checkJobProcessingRates();
        $stats['processing'] = $processStats;

        // Display results
        $this->displayResults($stats, $issues);

        // Log health check results
        $this->logHealthCheck($stats, $issues);

        return count($issues) === 0 ? 0 : 1;
    }

    protected function checkRedisConnection(): array
    {
        try {
            $redis = Redis::connection('queue');
            $redis->ping();

            return [
                'healthy' => true,
                'message' => '✅ Redis queue connection is healthy',
                'connection' => 'queue',
                'host' => config('database.redis.queue.host'),
                'port' => config('database.redis.queue.port'),
                'database' => config('database.redis.queue.database'),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => "❌ Redis connection failed: {$e->getMessage()}",
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function checkQueueSizes(): array
    {
        $queues = ['high', 'certificates', 'warehouse', 'default', 'low'];
        $stats = [];

        try {
            $redis = Redis::connection('queue');

            foreach ($queues as $queue) {
                $queueKey = config('database.redis.options.prefix')."queues:{$queue}";
                $size = $redis->llen($queueKey);

                $stats[$queue] = [
                    'size' => $size,
                    'status' => $size < 50 ? 'healthy' : ($size < 100 ? 'warning' : 'critical'),
                    'key' => $queueKey,
                ];
            }
        } catch (\Exception $e) {
            $this->error("Failed to check queue sizes: {$e->getMessage()}");
        }

        return $stats;
    }

    protected function checkFailedJobs(): array
    {
        try {
            $count = DB::table('failed_jobs')->count();
            $recentCount = DB::table('failed_jobs')
                ->where('failed_at', '>', Carbon::now()->subHour())
                ->count();

            return [
                'count' => $count,
                'recent_count' => $recentCount,
                'status' => $count < 10 ? 'healthy' : ($count < 50 ? 'warning' : 'critical'),
            ];
        } catch (\Exception $e) {
            return [
                'count' => 0,
                'recent_count' => 0,
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function checkJobProcessingRates(): array
    {
        try {
            // Check job progress records for recent activity
            $recentJobs = DB::table('job_progress')
                ->where('created_at', '>', Carbon::now()->subMinutes(30))
                ->get();

            $completed = $recentJobs->where('status', 'completed')->count();
            $failed = $recentJobs->where('status', 'failed')->count();
            $processing = $recentJobs->where('status', 'processing')->count();

            $successRate = $recentJobs->count() > 0 ?
                round(($completed / $recentJobs->count()) * 100, 2) : 100;

            return [
                'total_recent' => $recentJobs->count(),
                'completed' => $completed,
                'failed' => $failed,
                'processing' => $processing,
                'success_rate' => $successRate,
                'status' => $successRate > 90 ? 'healthy' : ($successRate > 70 ? 'warning' : 'critical'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function displayResults(array $stats, array $issues): void
    {
        // Display overall status
        if (empty($issues)) {
            $this->info('🎉 All queue systems are healthy!');
        } else {
            $this->warn('⚠️ Found '.count($issues).' issue(s):');
            foreach ($issues as $issue) {
                $this->line("  {$issue}");
            }
        }

        // Display Redis status
        $this->newLine();
        $this->line($stats['redis']['message']);

        // Display queue sizes
        $this->newLine();
        $this->info('📊 Queue Sizes:');
        foreach ($stats['queues'] as $queue => $info) {
            $status = match ($info['status']) {
                'healthy' => '🟢',
                'warning' => '🟡',
                'critical' => '🔴',
                default => '⚪'
            };
            $this->line("  {$status} {$queue}: {$info['size']} jobs");
        }

        // Display failed jobs
        $this->newLine();
        $failedStatus = match ($stats['failed_jobs']['status']) {
            'healthy' => '🟢',
            'warning' => '🟡',
            'critical' => '🔴',
            default => '⚪'
        };
        $this->line("{$failedStatus} Failed Jobs: {$stats['failed_jobs']['count']} total, {$stats['failed_jobs']['recent_count']} in last hour");

        // Display processing stats
        if (isset($stats['processing']['total_recent'])) {
            $this->newLine();
            $processingStatus = match ($stats['processing']['status']) {
                'healthy' => '🟢',
                'warning' => '🟡',
                'critical' => '🔴',
                default => '⚪'
            };
            $this->line("{$processingStatus} Job Processing (last 30 min): {$stats['processing']['total_recent']} jobs, {$stats['processing']['success_rate']}% success rate");
        }

        // Show detailed stats if requested
        if ($this->option('detailed')) {
            $this->displayDetailedStats($stats);
        }
    }

    protected function displayDetailedStats(array $stats): void
    {
        $this->newLine();
        $this->info('📋 Detailed Statistics:');

        // Redis details
        if (isset($stats['redis']['host'])) {
            $this->line("  Redis: {$stats['redis']['host']}:{$stats['redis']['port']} (DB: {$stats['redis']['database']})");
        }

        // Queue details
        foreach ($stats['queues'] as $queue => $info) {
            $this->line("  {$queue} queue key: {$info['key']}");
        }

        // Processing details
        if (isset($stats['processing']['completed'])) {
            $this->line("  Recent jobs - Completed: {$stats['processing']['completed']}, Failed: {$stats['processing']['failed']}, Processing: {$stats['processing']['processing']}");
        }
    }

    protected function logHealthCheck(array $stats, array $issues): void
    {
        $logData = [
            'timestamp' => now()->toISOString(),
            'stats' => $stats,
            'issues_count' => count($issues),
            'issues' => $issues,
            'overall_status' => empty($issues) ? 'healthy' : 'issues_found',
        ];

        if (! empty($issues)) {
            Log::warning('Queue health check found issues', $logData);
        } else {
            Log::info('Queue health check passed', $logData);
        }
    }
}
