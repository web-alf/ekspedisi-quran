<?php

namespace App\Console\Commands\Monitoring;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SetupMonitoringCommand extends Command
{
    protected $signature = 'monitoring:setup 
                            {--force : Force setup even if already initialized}
                            {--skip-tables : Skip database table creation}
                            {--skip-cache : Skip cache setup}
                            {--skip-storage : Skip storage setup}';

    protected $description = 'Set up monitoring infrastructure and initialize the monitoring system';

    public function handle()
    {
        $this->info('🔧 Setting up monitoring infrastructure...');

        $force = $this->option('force');

        // Check if already initialized
        if (! $force && $this->isAlreadyInitialized()) {
            $this->warn('Monitoring system is already initialized. Use --force to reinitialize.');

            return 1;
        }

        $steps = [
            'Database Tables' => 'setupDatabaseTables',
            'Cache Structure' => 'setupCacheStructure',
            'Storage Directories' => 'setupStorageDirectories',
            'Configuration' => 'setupConfiguration',
            'Permissions' => 'setupPermissions',
            'Scheduled Tasks' => 'setupScheduledTasks',
            'Initial Baselines' => 'setupInitialBaselines',
        ];

        $completed = 0;
        $total = count($steps);

        foreach ($steps as $stepName => $method) {
            if ($this->shouldSkipStep($stepName)) {
                $this->line("⏭️  Skipping {$stepName}");

                continue;
            }

            $this->line("🔄 Setting up {$stepName}...");

            try {
                $this->{$method}();
                $completed++;
                $this->info("✅ {$stepName} completed");
            } catch (\Exception $e) {
                $this->error("❌ {$stepName} failed: {$e->getMessage()}");

                if (! $this->confirm('Continue with remaining setup steps?')) {
                    return 1;
                }
            }

            $progress = round(($completed / $total) * 100);
            $this->line("Progress: {$progress}%");
            $this->newLine();
        }

        // Mark as initialized
        $this->markAsInitialized();

        $this->newLine();
        $this->info('🎉 Monitoring system setup completed successfully!');
        $this->newLine();

        $this->displayNextSteps();

        return 0;
    }

    protected function isAlreadyInitialized(): bool
    {
        try {
            return Cache::has('monitoring_initialized');
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function shouldSkipStep(string $stepName): bool
    {
        return match ($stepName) {
            'Database Tables' => $this->option('skip-tables'),
            'Cache Structure' => $this->option('skip-cache'),
            'Storage Directories' => $this->option('skip-storage'),
            default => false
        };
    }

    protected function setupDatabaseTables(): void
    {
        // Check if tables exist
        $tables = [
            'monitoring_metrics',
            'monitoring_alerts',
            'monitoring_benchmarks',
            'performance_baselines',
        ];

        foreach ($tables as $table) {
            if (! $this->tableExists($table)) {
                $this->createTable($table);
            }
        }
    }

    protected function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function createTable(string $table): void
    {
        $this->line("  📊 Creating table: {$table}");

        switch ($table) {
            case 'monitoring_metrics':
                DB::statement('
                    CREATE TABLE IF NOT EXISTS monitoring_metrics (
                        id BIGINT PRIMARY KEY AUTO_INCREMENT,
                        timestamp DATETIME NOT NULL,
                        category VARCHAR(50) NOT NULL,
                        metric_name VARCHAR(100) NOT NULL,
                        metric_value DECIMAL(15,4),
                        metric_data JSON,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_timestamp (timestamp),
                        INDEX idx_category_metric (category, metric_name)
                    )
                ');
                break;

            case 'monitoring_alerts':
                DB::statement("
                    CREATE TABLE IF NOT EXISTS monitoring_alerts (
                        id BIGINT PRIMARY KEY AUTO_INCREMENT,
                        alert_id VARCHAR(100) UNIQUE NOT NULL,
                        level ENUM('info', 'warning', 'critical', 'emergency') NOT NULL,
                        type VARCHAR(50) NOT NULL,
                        metric VARCHAR(100) NOT NULL,
                        message TEXT NOT NULL,
                        current_value DECIMAL(15,4),
                        threshold_value DECIMAL(15,4),
                        context JSON,
                        resolved_at DATETIME NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_level (level),
                        INDEX idx_type (type),
                        INDEX idx_resolved (resolved_at)
                    )
                ");
                break;

            case 'monitoring_benchmarks':
                DB::statement('
                    CREATE TABLE IF NOT EXISTS monitoring_benchmarks (
                        id BIGINT PRIMARY KEY AUTO_INCREMENT,
                        benchmark_id VARCHAR(100) NOT NULL,
                        category VARCHAR(50) NOT NULL,
                        results JSON NOT NULL,
                        scores JSON NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_category (category),
                        INDEX idx_created_at (created_at)
                    )
                ');
                break;

            case 'performance_baselines':
                DB::statement('
                    CREATE TABLE IF NOT EXISTS performance_baselines (
                        id BIGINT PRIMARY KEY AUTO_INCREMENT,
                        category VARCHAR(50) NOT NULL,
                        metric_name VARCHAR(100) NOT NULL,
                        baseline_value DECIMAL(15,4) NOT NULL,
                        confidence_level DECIMAL(5,2) DEFAULT 95.00,
                        sample_size INT DEFAULT 1,
                        set_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uk_category_metric (category, metric_name)
                    )
                ');
                break;
        }
    }

    protected function setupCacheStructure(): void
    {
        $this->line('  🗄️ Setting up cache structure...');

        try {
            // Test cache functionality
            Cache::put('monitoring_cache_test', 'test_value', 60);
            $value = Cache::get('monitoring_cache_test');

            if ($value !== 'test_value') {
                throw new \Exception('Cache read/write test failed');
            }

            Cache::forget('monitoring_cache_test');

            // Initialize cache keys
            $initialKeys = [
                'monitoring_active_alerts' => [],
                'monitoring_system_health' => 'healthy',
                'monitoring_last_collection' => now()->toISOString(),
                'performance_baselines' => $this->getDefaultBaselines(),
            ];

            foreach ($initialKeys as $key => $value) {
                if (! Cache::has($key)) {
                    Cache::put($key, $value, 86400 * 7); // 7 days
                }
            }

        } catch (\Exception $e) {
            throw new \Exception("Cache setup failed: {$e->getMessage()}");
        }
    }

    protected function setupStorageDirectories(): void
    {
        $this->line('  📁 Setting up storage directories...');

        $directories = [
            storage_path('monitoring'),
            storage_path('monitoring/reports'),
            storage_path('monitoring/exports'),
            storage_path('monitoring/benchmarks'),
            storage_path('monitoring/alerts'),
            storage_path('logs/monitoring'),
        ];

        foreach ($directories as $dir) {
            if (! is_dir($dir)) {
                if (! mkdir($dir, 0755, true)) {
                    throw new \Exception("Failed to create directory: {$dir}");
                }
                $this->line("    Created: {$dir}");
            }
        }

        // Create .gitignore files
        $gitignoreContent = "*\n!.gitignore\n";
        foreach ($directories as $dir) {
            $gitignoreFile = $dir.'/.gitignore';
            if (! file_exists($gitignoreFile)) {
                file_put_contents($gitignoreFile, $gitignoreContent);
            }
        }
    }

    protected function setupConfiguration(): void
    {
        $this->line('  ⚙️ Setting up configuration...');

        // Create environment variables if not exist
        $envFile = base_path('.env');
        $envContent = file_get_contents($envFile);

        $monitoringVars = [
            'MONITORING_ENABLED=true',
            'MONITORING_COLLECTION_INTERVAL=60',
            'MONITORING_RETENTION_PERIOD=604800',
            'MONITORING_ALERTING_ENABLED=true',
            'MONITORING_ALERT_COOLDOWN=300',
            'MONITORING_EMAIL_ALERTS=false',
            'MONITORING_WEBHOOK_ALERTS=false',
            '# MONITORING_ALERT_EMAIL=admin@example.com',
            '# MONITORING_WEBHOOK_URL=https://hooks.example.com/monitoring',
        ];

        $newVars = [];
        foreach ($monitoringVars as $var) {
            $key = explode('=', $var)[0];
            if (strpos($envContent, $key) === false) {
                $newVars[] = $var;
            }
        }

        if (! empty($newVars)) {
            $newContent = $envContent."\n\n# Monitoring Configuration\n".implode("\n", $newVars)."\n";
            file_put_contents($envFile, $newContent);
            $this->line('    Added monitoring configuration to .env file');
        }
    }

    protected function setupPermissions(): void
    {
        $this->line('  🔐 Setting up permissions...');

        try {
            // Check if Spatie permissions is available
            if (class_exists('\Spatie\Permission\Models\Permission')) {
                $permissions = [
                    'view monitoring dashboard',
                    'manage monitoring alerts',
                    'run performance benchmarks',
                    'export monitoring reports',
                    'configure monitoring settings',
                ];

                foreach ($permissions as $permission) {
                    \Spatie\Permission\Models\Permission::firstOrCreate([
                        'name' => $permission,
                        'guard_name' => 'web',
                    ]);
                }

                $this->line('    Created monitoring permissions');
            }
        } catch (\Exception $e) {
            $this->warn("    Permissions setup skipped: {$e->getMessage()}");
        }
    }

    protected function setupScheduledTasks(): void
    {
        $this->line('  📅 Setting up scheduled tasks...');

        // This would typically add to the scheduler, but we'll just document
        $scheduledCommands = [
            'monitoring:collect-metrics' => 'Every minute',
            'monitoring:health-check' => 'Every 5 minutes',
            'monitoring:cleanup' => 'Daily at 2:00 AM',
            'queue:monitor' => 'Continuous (supervisor)',
        ];

        $this->line('    The following commands should be scheduled:');
        foreach ($scheduledCommands as $command => $frequency) {
            $this->line("      {$command} - {$frequency}");
        }

        // Create a sample supervisor config
        $supervisorConfig = storage_path('monitoring/supervisor-monitoring.conf');
        $supervisorContent = $this->getSupervisorConfig();
        file_put_contents($supervisorConfig, $supervisorContent);

        $this->line("    Sample supervisor config created: {$supervisorConfig}");
    }

    protected function setupInitialBaselines(): void
    {
        $this->line('  📊 Setting up initial performance baselines...');

        try {
            $benchmarkService = app(\App\Services\Monitoring\PerformanceBenchmarkService::class);
            $results = $benchmarkService->runBenchmarks();
            $benchmarkService->setBaselines($results['benchmarks']);

            $this->line('    Initial performance baselines established');
        } catch (\Exception $e) {
            $this->warn("    Baseline setup failed: {$e->getMessage()}");
        }
    }

    protected function markAsInitialized(): void
    {
        Cache::put('monitoring_initialized', [
            'initialized_at' => now()->toISOString(),
            'version' => '1.0.0',
            'components' => [
                'metrics_collection' => true,
                'alerting' => true,
                'benchmarks' => true,
                'dashboard' => true,
            ],
        ], 86400 * 365); // 1 year

        // Create a file marker as well
        file_put_contents(
            storage_path('monitoring/.initialized'),
            json_encode(['timestamp' => now()->toISOString()])
        );
    }

    protected function displayNextSteps(): void
    {
        $this->info('Next steps:');
        $this->line('1. Configure your .env file with monitoring settings');
        $this->line('2. Set up the supervisor configuration for queue monitoring');
        $this->line('3. Add monitoring commands to your cron schedule');
        $this->line('4. Access the monitoring dashboard at /admin/monitoring');
        $this->line('5. Configure alerting channels (email, webhook, etc.)');

        $this->newLine();
        $this->info('Available commands:');
        $this->line('- php artisan monitoring:collect-metrics     # Collect current metrics');
        $this->line('- php artisan monitoring:health-check       # Run health check');
        $this->line('- php artisan queue:monitor                 # Monitor queue performance');
        $this->line('- php artisan monitoring:benchmarks         # Run performance benchmarks');

        $this->newLine();
        $this->line('Dashboard URL: '.url('/admin/monitoring'));
    }

    protected function getDefaultBaselines(): array
    {
        return [
            'database' => [
                'simple_query_100x' => 100,
                'complex_query' => 50,
                'connection_overhead_10x' => 20,
            ],
            'cache' => [
                'read_1kb_100x' => 10,
                'write_1kb_100x' => 15,
                'read_100kb' => 5,
                'write_100kb' => 8,
            ],
            'file_io' => [
                'read_1kb_100_files' => 50,
                'write_1kb_100_files' => 75,
                'read_1mb' => 20,
                'write_1mb' => 30,
            ],
            'memory' => [
                'array_populate_100k' => 50,
                'string_concatenation_10k' => 25,
                'json_encode_1k_objects' => 15,
                'json_decode_1k_objects' => 20,
            ],
            'cpu' => [
                'math_operations_100k' => 100,
                'string_manipulation_10k' => 75,
                'regex_operations_1k' => 50,
                'md5_hash_10k' => 25,
            ],
        ];
    }

    protected function getSupervisorConfig(): string
    {
        return <<<'CONF'
[program:laravel-monitoring-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:listen --queue=monitoring --tries=3 --timeout=60
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/monitoring-queue.log
numprocs=1

[program:laravel-metrics-collector]
process_name=%(program_name)s
command=php /path/to/your/project/artisan monitoring:collect-metrics --interval=60
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/metrics-collector.log
numprocs=1

[program:laravel-queue-monitor]
process_name=%(program_name)s
command=php /path/to/your/project/artisan queue:monitor --interval=30 --duration=0
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/queue-monitor.log
numprocs=1
CONF;
    }
}
