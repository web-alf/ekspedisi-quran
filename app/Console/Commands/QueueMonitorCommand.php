<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class QueueMonitorCommand extends Command
{
    protected $signature = 'queue:monitor 
                            {--interval=10 : Monitor interval in seconds}
                            {--duration=300 : Total monitoring duration in seconds}
                            {--export-csv= : Export results to CSV file}';

    protected $description = 'Monitor queue performance and worker activity in real-time';

    protected array $history = [];

    protected int $startTime;

    public function handle()
    {
        $interval = (int) $this->option('interval');
        $duration = (int) $this->option('duration');
        $this->startTime = time();

        $this->info("🚀 Starting Queue Monitor for {$duration} seconds (checking every {$interval}s)");
        $this->info('Press Ctrl+C to stop monitoring');

        // Set up signal handling for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
        }

        $endTime = $this->startTime + $duration;

        while (time() < $endTime) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            $snapshot = $this->collectSnapshot();
            $this->history[] = $snapshot;
            $this->displaySnapshot($snapshot);

            if (time() + $interval < $endTime) {
                sleep($interval);
            } else {
                break;
            }
        }

        $this->displaySummary();

        if ($csvFile = $this->option('export-csv')) {
            $this->exportToCsv($csvFile);
        }

        return 0;
    }

    protected function collectSnapshot(): array
    {
        $timestamp = Carbon::now();
        $snapshot = [
            'timestamp' => $timestamp,
            'unix_time' => $timestamp->timestamp,
            'queues' => [],
            'failed_jobs' => 0,
            'job_progress' => [
                'processing' => 0,
                'completed_last_minute' => 0,
                'failed_last_minute' => 0,
            ],
        ];

        // Collect queue sizes
        try {
            $redis = Redis::connection('queue');
            $queues = ['high', 'certificates', 'warehouse', 'default', 'low'];

            foreach ($queues as $queue) {
                $queueKey = config('database.redis.options.prefix')."queues:{$queue}";
                $size = $redis->llen($queueKey);
                $snapshot['queues'][$queue] = $size;
            }
        } catch (\Exception $e) {
            $this->error("Redis error: {$e->getMessage()}");
        }

        // Collect failed jobs count
        try {
            $snapshot['failed_jobs'] = DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            // Ignore database errors for monitoring
        }

        // Collect job progress stats
        try {
            $snapshot['job_progress']['processing'] = DB::table('job_progress')
                ->where('status', 'processing')
                ->count();

            $snapshot['job_progress']['completed_last_minute'] = DB::table('job_progress')
                ->where('status', 'completed')
                ->where('updated_at', '>', $timestamp->subMinute())
                ->count();

            $snapshot['job_progress']['failed_last_minute'] = DB::table('job_progress')
                ->where('status', 'failed')
                ->where('updated_at', '>', $timestamp->subMinute())
                ->count();
        } catch (\Exception $e) {
            // Ignore database errors for monitoring
        }

        return $snapshot;
    }

    protected function displaySnapshot(array $snapshot): void
    {
        // Clear screen and move cursor to top
        echo "\033[2J\033[H";

        $this->info('📊 Queue Monitor - '.$snapshot['timestamp']->format('Y-m-d H:i:s'));
        $this->info('Duration: '.(time() - $this->startTime).'s | Snapshots: '.count($this->history));

        $this->newLine();
        $this->info('Queue Sizes:');

        $totalJobs = 0;
        foreach ($snapshot['queues'] as $queue => $size) {
            $totalJobs += $size;
            $trend = $this->calculateTrend($queue, $size);
            $trendSymbol = match (true) {
                $trend > 0 => '📈',
                $trend < 0 => '📉',
                default => '➡️'
            };

            $status = match (true) {
                $size === 0 => '🟢',
                $size < 10 => '🟡',
                $size < 50 => '🟠',
                default => '🔴'
            };

            $this->line("  {$status} {$trendSymbol} {$queue}: {$size}");
        }

        $this->newLine();
        $this->line("📦 Total Queue Jobs: {$totalJobs}");
        $this->line("❌ Failed Jobs: {$snapshot['failed_jobs']}");

        $this->newLine();
        $this->info('Job Processing (Last Minute):');
        $this->line("  🔄 Currently Processing: {$snapshot['job_progress']['processing']}");
        $this->line("  ✅ Completed: {$snapshot['job_progress']['completed_last_minute']}");
        $this->line("  ❌ Failed: {$snapshot['job_progress']['failed_last_minute']}");

        // Show processing rate if we have history
        if (count($this->history) > 1) {
            $this->displayProcessingRate();
        }
    }

    protected function calculateTrend(string $queue, int $currentSize): int
    {
        if (count($this->history) < 2) {
            return 0;
        }

        $previousSnapshot = $this->history[count($this->history) - 2];
        $previousSize = $previousSnapshot['queues'][$queue] ?? 0;

        return $currentSize - $previousSize;
    }

    protected function displayProcessingRate(): void
    {
        $current = end($this->history);
        $previous = $this->history[count($this->history) - 2];

        $timeDiff = $current['unix_time'] - $previous['unix_time'];

        if ($timeDiff > 0) {
            $completedDiff = $current['job_progress']['completed_last_minute'] - $previous['job_progress']['completed_last_minute'];
            $rate = round($completedDiff / ($timeDiff / 60), 2); // jobs per minute

            $this->newLine();
            $this->line("⚡ Processing Rate: ~{$rate} jobs/min");
        }
    }

    protected function displaySummary(): void
    {
        if (empty($this->history)) {
            return;
        }

        $this->newLine(2);
        $this->info('📈 Monitoring Summary:');

        $first = reset($this->history);
        $last = end($this->history);

        $duration = $last['unix_time'] - $first['unix_time'];
        $this->line("Duration: {$duration} seconds");
        $this->line('Snapshots: '.count($this->history));

        $this->newLine();
        $this->info('Queue Changes:');

        foreach (['high', 'certificates', 'warehouse', 'default', 'low'] as $queue) {
            $startSize = $first['queues'][$queue] ?? 0;
            $endSize = $last['queues'][$queue] ?? 0;
            $change = $endSize - $startSize;

            $changeSymbol = match (true) {
                $change > 0 => "+{$change} 📈",
                $change < 0 => "{$change} 📉",
                default => '0 ➡️'
            };

            $this->line("  {$queue}: {$startSize} → {$endSize} ({$changeSymbol})");
        }

        // Calculate processing statistics
        $totalCompleted = 0;
        $totalFailed = 0;

        foreach ($this->history as $snapshot) {
            $totalCompleted += $snapshot['job_progress']['completed_last_minute'];
            $totalFailed += $snapshot['job_progress']['failed_last_minute'];
        }

        if ($totalCompleted + $totalFailed > 0) {
            $successRate = round(($totalCompleted / ($totalCompleted + $totalFailed)) * 100, 2);
            $this->newLine();
            $this->line("✅ Total Jobs Completed: {$totalCompleted}");
            $this->line("❌ Total Jobs Failed: {$totalFailed}");
            $this->line("📊 Success Rate: {$successRate}%");
        }
    }

    protected function exportToCsv(string $filename): void
    {
        if (empty($this->history)) {
            $this->warn('No data to export');

            return;
        }

        $file = fopen($filename, 'w');

        // Write header
        fputcsv($file, [
            'timestamp',
            'high_queue',
            'certificates_queue',
            'warehouse_queue',
            'default_queue',
            'low_queue',
            'failed_jobs',
            'processing_jobs',
            'completed_last_minute',
            'failed_last_minute',
        ]);

        // Write data
        foreach ($this->history as $snapshot) {
            fputcsv($file, [
                $snapshot['timestamp']->toISOString(),
                $snapshot['queues']['high'] ?? 0,
                $snapshot['queues']['certificates'] ?? 0,
                $snapshot['queues']['warehouse'] ?? 0,
                $snapshot['queues']['default'] ?? 0,
                $snapshot['queues']['low'] ?? 0,
                $snapshot['failed_jobs'],
                $snapshot['job_progress']['processing'],
                $snapshot['job_progress']['completed_last_minute'],
                $snapshot['job_progress']['failed_last_minute'],
            ]);
        }

        fclose($file);
        $this->info("📊 Data exported to: {$filename}");
    }

    public function handleShutdown(): void
    {
        $this->newLine(2);
        $this->info('🛑 Shutting down monitor...');
        $this->displaySummary();

        if ($csvFile = $this->option('export-csv')) {
            $this->exportToCsv($csvFile);
        }

        exit(0);
    }
}
