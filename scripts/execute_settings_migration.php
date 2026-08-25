<?php
/**
 * Settings Migration Execution Script
 * 
 * This script executes the complete settings migration process
 * with proper error handling and rollback capabilities.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;

class SettingsMigrationExecutor
{
    private $migrations = [
        '2025_08_13_172128_migrate_contact_settings_to_contact_information_table',
        '2025_08_13_172205_create_seo_settings_table',
        '2025_08_13_172241_create_legal_pages_table',
        '2025_08_13_172325_create_landing_display_settings_table',
        '2025_08_13_172420_optimize_general_settings_organization'
    ];

    private $backupFile = null;

    public function execute($dryRun = false)
    {
        echo "Starting Settings Migration Process...\n\n";

        try {
            // Step 1: Create backup
            $this->createBackup();

            // Step 2: Verify pre-migration state
            $this->verifyPreMigration();

            if ($dryRun) {
                echo "DRY RUN MODE - No actual migration performed\n";
                return true;
            }

            // Step 3: Execute migrations
            $this->executeMigrations();

            // Step 4: Verify post-migration state
            $this->verifyPostMigration();

            // Step 5: Clear caches
            $this->clearCaches();

            echo "\n✅ Migration completed successfully!\n";
            return true;

        } catch (\Exception $e) {
            echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
            $this->handleFailure();
            return false;
        }
    }

    private function createBackup()
    {
        echo "📦 Creating backup...\n";
        
        $filename = 'migration_backup_' . date('Y_m_d_H_i_s') . '.json';
        $exitCode = Artisan::call('settings:backup', [
            '--filename' => $filename,
            '--include-migrated' => true
        ]);

        if ($exitCode !== 0) {
            throw new \Exception('Backup creation failed');
        }

        $this->backupFile = $filename;
        echo "✅ Backup created: storage/app/backups/settings/{$filename}\n\n";
    }

    private function verifyPreMigration()
    {
        echo "🔍 Verifying pre-migration state...\n";
        
        $exitCode = Artisan::call('settings:verify', [
            '--detailed' => true
        ]);

        if ($exitCode !== 0) {
            throw new \Exception('Pre-migration verification failed');
        }

        echo "✅ Pre-migration verification completed\n\n";
    }

    private function executeMigrations()
    {
        echo "🚀 Executing migrations...\n";

        foreach ($this->migrations as $index => $migration) {
            $step = $index + 1;
            echo "Step {$step}/5: Running {$migration}...\n";
            
            $exitCode = Artisan::call('migrate', [
                '--path' => "database/migrations/{$migration}.php",
                '--force' => true
            ]);

            if ($exitCode !== 0) {
                throw new \Exception("Migration {$migration} failed");
            }

            echo "✅ Step {$step} completed\n";
        }

        echo "✅ All migrations executed successfully\n\n";
    }

    private function verifyPostMigration()
    {
        echo "🔍 Verifying post-migration state...\n";
        
        $exitCode = Artisan::call('settings:verify', [
            '--detailed' => true,
            '--check-integrity' => true
        ]);

        if ($exitCode !== 0) {
            throw new \Exception('Post-migration verification failed');
        }

        echo "✅ Post-migration verification completed\n\n";
    }

    private function clearCaches()
    {
        echo "🧹 Clearing caches...\n";
        
        $cacheCommands = ['cache:clear', 'config:clear', 'view:clear'];
        
        foreach ($cacheCommands as $command) {
            Artisan::call($command);
            echo "✅ Cleared {$command}\n";
        }
        
        echo "\n";
    }

    private function handleFailure()
    {
        echo "\n🔄 Attempting rollback...\n";
        
        try {
            // Rollback migrations in reverse order
            $exitCode = Artisan::call('migrate:rollback', [
                '--step' => count($this->migrations),
                '--force' => true
            ]);

            if ($exitCode === 0) {
                echo "✅ Rollback completed successfully\n";
            } else {
                echo "❌ Rollback failed - manual intervention required\n";
                echo "Use emergency restore: php storage/app/backups/restore_settings.php\n";
            }

        } catch (\Exception $e) {
            echo "❌ Rollback failed: " . $e->getMessage() . "\n";
            echo "Use emergency restore: php storage/app/backups/restore_settings.php\n";
        }
    }
}

// Script execution
if (php_sapi_name() === 'cli') {
    $dryRun = in_array('--dry-run', $argv);
    
    $executor = new SettingsMigrationExecutor();
    $success = $executor->execute($dryRun);
    
    exit($success ? 0 : 1);
}