<?php

namespace App\Console\Commands;

use App\Services\Cache\CacheManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CacheManagementCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cache:manage 
                            {action : Action to perform (warm, clear, stats, health, test)}
                            {--service= : Specific service to target (dashboard, reference, geographic, user, query)}
                            {--force : Force the action without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Manage hierarchical performance cache system';

    protected CacheManager $cacheManager;

    public function __construct(CacheManager $cacheManager)
    {
        parent::__construct();
        $this->cacheManager = $cacheManager;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $service = $this->option('service');

        $this->info('Performance Cache Management');
        $this->info("Action: {$action}");

        if ($service) {
            $this->info("Service: {$service}");
        }

        try {
            switch ($action) {
                case 'warm':
                    return $this->handleWarm($service);
                case 'clear':
                    return $this->handleClear($service);
                case 'stats':
                    return $this->handleStats($service);
                case 'health':
                    return $this->handleHealth($service);
                case 'test':
                    return $this->handleTest($service);
                default:
                    $this->error("Invalid action: {$action}");
                    $this->info('Available actions: warm, clear, stats, health, test');

                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Command failed: '.$e->getMessage());
            Log::error('Cache management command failed', [
                'action' => $action,
                'service' => $service,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Handle cache warming
     */
    protected function handleWarm(?string $service): int
    {
        if ($service) {
            $this->info("Warming cache for service: {$service}");

            $cacheService = $this->cacheManager->getService($service);
            if (! $cacheService) {
                $this->error("Service not found: {$service}");

                return Command::FAILURE;
            }

            $startTime = microtime(true);
            $result = $cacheService->warm();
            $endTime = microtime(true);

            if ($result) {
                $this->info("✓ Cache warming completed for {$service} in ".
                    round(($endTime - $startTime) * 1000, 2).'ms');

                return Command::SUCCESS;
            } else {
                $this->error("✗ Cache warming failed for {$service}");

                return Command::FAILURE;
            }
        }

        // Warm all caches
        $this->info('Warming all caches...');
        $this->withProgressBar($this->cacheManager->getServices(), function ($cacheService, $serviceName) {
            $cacheService->warm();
        });
        $this->newLine();

        $result = $this->cacheManager->warmAll();

        if ($result['success']) {
            $this->info('✓ All caches warmed successfully');
            $this->table(['Service', 'Status', 'Time (s)'],
                collect($result['results'])->map(function ($result, $service) {
                    return [
                        $service,
                        $result['success'] ? '✓ Success' : '✗ Failed',
                        $result['time_taken'],
                    ];
                })->toArray()
            );

            return Command::SUCCESS;
        } else {
            $this->error('✗ Some caches failed to warm');

            return Command::FAILURE;
        }
    }

    /**
     * Handle cache clearing
     */
    protected function handleClear(?string $service): int
    {
        if ($service) {
            if (! $this->option('force') && ! $this->confirm("Clear cache for service: {$service}?")) {
                $this->info('Cache clearing cancelled.');

                return Command::SUCCESS;
            }

            $cacheService = $this->cacheManager->getService($service);
            if (! $cacheService) {
                $this->error("Service not found: {$service}");

                return Command::FAILURE;
            }

            $result = $cacheService->flushAll();

            if ($result) {
                $this->info("✓ Cache cleared for {$service}");

                return Command::SUCCESS;
            } else {
                $this->error("✗ Failed to clear cache for {$service}");

                return Command::FAILURE;
            }
        }

        // Clear all caches
        if (! $this->option('force') && ! $this->confirm('Clear ALL caches?')) {
            $this->info('Cache clearing cancelled.');

            return Command::SUCCESS;
        }

        $result = $this->cacheManager->clearAll();

        if ($result['success']) {
            $this->info('✓ All caches cleared successfully');

            return Command::SUCCESS;
        } else {
            $this->error('✗ Some caches failed to clear');

            return Command::FAILURE;
        }
    }

    /**
     * Handle cache statistics
     */
    protected function handleStats(?string $service): int
    {
        if ($service) {
            $cacheService = $this->cacheManager->getService($service);
            if (! $cacheService) {
                $this->error("Service not found: {$service}");

                return Command::FAILURE;
            }

            $stats = $cacheService->getStats();
            $this->displayServiceStats($service, $stats);

            return Command::SUCCESS;
        }

        // Show all cache statistics
        $stats = $this->cacheManager->getStats();

        $this->info('=== Cache System Overview ===');
        $this->table(['Metric', 'Value'], [
            ['Cache Driver', $stats['cache_driver']],
            ['Total Services', $stats['summary']['services_count']],
            ['Total Cache Keys', $stats['summary']['total_cache_keys']],
            ['Estimated Size (MB)', $stats['summary']['estimated_total_size_mb']],
            ['Generated At', $stats['generated_at']],
        ]);

        $this->newLine();
        $this->info('=== Service Statistics ===');

        foreach ($stats['services'] as $serviceName => $serviceStats) {
            $this->displayServiceStats($serviceName, $serviceStats);
        }

        if (isset($stats['redis']) && is_array($stats['redis'])) {
            $this->newLine();
            $this->info('=== Redis Statistics ===');
            $this->table(['Metric', 'Value'],
                collect($stats['redis'])->map(function ($value, $key) {
                    return [ucwords(str_replace('_', ' ', $key)), $value];
                })->toArray()
            );
        }

        return Command::SUCCESS;
    }

    /**
     * Display service statistics
     */
    protected function displayServiceStats(string $serviceName, array $stats): void
    {
        $this->info("--- {$serviceName} ---");

        if (isset($stats['error'])) {
            $this->error('Error: '.$stats['error']);

            return;
        }

        $rows = [];
        foreach ($stats as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $rows[] = [ucwords(str_replace('_', ' ', $key)), $value];
        }

        $this->table(['Metric', 'Value'], $rows);
        $this->newLine();
    }

    /**
     * Handle health check
     */
    protected function handleHealth(?string $service): int
    {
        if ($service) {
            $cacheService = $this->cacheManager->getService($service);
            if (! $cacheService) {
                $this->error("Service not found: {$service}");

                return Command::FAILURE;
            }

            $health = $cacheService->health();

            if ($health['healthy']) {
                $this->info("✓ {$service} cache is healthy");
            } else {
                $this->error("✗ {$service} cache is unhealthy");
                if (isset($health['error'])) {
                    $this->error('Error: '.$health['error']);
                }
            }

            return $health['healthy'] ? Command::SUCCESS : Command::FAILURE;
        }

        // Check health of all services
        $health = $this->cacheManager->getHealth();

        $this->info('=== Cache System Health Check ===');
        $this->info('Overall Status: '.($health['overall_healthy'] ? '✓ Healthy' : '✗ Unhealthy'));
        $this->info('Cache Driver: '.$health['cache_driver']);
        $this->info('Tested At: '.$health['tested_at']);

        $this->newLine();
        $this->table(['Service', 'Status', 'Details'],
            collect($health['services'])->map(function ($serviceHealth, $service) {
                $status = $serviceHealth['healthy'] ? '✓ Healthy' : '✗ Unhealthy';
                $details = $serviceHealth['error'] ??
                    ($serviceHealth['tested_at'] ?? 'No details');

                return [$service, $status, $details];
            })->toArray()
        );

        return $health['overall_healthy'] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Handle cache testing
     */
    protected function handleTest(?string $service): int
    {
        $this->info('Running cache functionality tests...');

        $results = $this->cacheManager->testCache();

        $this->info('=== Cache Test Results ===');
        $this->info('Overall: '.($results['success'] ? '✓ Passed' : '✗ Failed'));
        $this->info('Tested At: '.$results['tested_at']);

        $this->newLine();
        $this->table(['Service', 'Write', 'Read', 'Delete', 'Overall', 'Error'],
            collect($results['results'])->map(function ($result, $service) {
                return [
                    $service,
                    $result['write'] ? '✓' : '✗',
                    $result['read'] ? '✓' : '✗',
                    $result['delete'] ? '✓' : '✗',
                    $result['overall'] ? '✓' : '✗',
                    $result['error'] ?? '-',
                ];
            })->toArray()
        );

        return $results['success'] ? Command::SUCCESS : Command::FAILURE;
    }
}
