<?php

namespace App\Console\Commands;

use App\Jobs\Certificate\CleanupOldCertificatesJob;
use App\Services\CertificateStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CertificateCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'certificates:cleanup
                          {--days=30 : Number of days to keep files}
                          {--type=all : Type of cleanup (certificates, jobs, temp, zip, all)}
                          {--force : Force cleanup even for important files}
                          {--dry-run : Show what would be cleaned without actually deleting}
                          {--stats : Show storage statistics}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old certificate files and job records';

    /**
     * Execute the console command.
     */
    public function handle(CertificateStorageService $storageService)
    {
        $daysToKeep = (int) $this->option('days');
        $cleanupType = $this->option('type');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $showStats = $this->option('stats');

        // Show current storage statistics
        if ($showStats || $dryRun) {
            $this->showStorageStats($storageService);
        }

        if ($showStats && ! $dryRun) {
            return 0; // Exit if only showing stats
        }

        if ($dryRun) {
            $this->info('DRY RUN - No files will actually be deleted');
            $this->newLine();
        }

        // Validate cleanup type
        $validTypes = ['certificates', 'jobs', 'temp', 'zip', 'all'];
        if (! in_array($cleanupType, $validTypes)) {
            $this->error('Invalid cleanup type. Must be one of: '.implode(', ', $validTypes));

            return 1;
        }

        // Confirm if force cleanup is requested
        if ($force && ! $this->confirm('Force cleanup will remove files regardless of their status. Continue?')) {
            $this->info('Cleanup cancelled.');

            return 0;
        }

        $this->info('Starting certificate cleanup...');
        $this->info("Days to keep: {$daysToKeep}");
        $this->info("Cleanup type: {$cleanupType}");
        $this->info('Force cleanup: '.($force ? 'Yes' : 'No'));
        $this->newLine();

        if ($dryRun) {
            // Simulate cleanup without actually deleting files
            $this->simulateCleanup($storageService, $daysToKeep, $cleanupType);
        } else {
            // Dispatch cleanup job
            $this->dispatchCleanupJob($daysToKeep, $cleanupType, $force);
        }

        return 0;
    }

    /**
     * Show storage statistics
     */
    private function showStorageStats(CertificateStorageService $storageService): void
    {
        $this->info('Current Storage Statistics:');
        $this->newLine();

        $stats = $storageService->getStorageStats();

        if (isset($stats['error'])) {
            $this->error("Error getting storage stats: {$stats['error']}");

            return;
        }

        // Main statistics table
        $this->table(
            ['Category', 'Files', 'Size (MB)'],
            [
                ['Certificates', $stats['certificates']['file_count'], $stats['certificates']['size_mb']],
                ['Temporary Files', $stats['temp']['file_count'], $stats['temp']['size_mb']],
                ['ZIP Files', $stats['zip']['file_count'], $stats['zip']['size_mb']],
                ['TOTAL', $stats['total_files'], $stats['total_size_mb']],
            ]
        );

        // File age information
        if (isset($stats['oldest_file'], $stats['newest_file'])) {
            $this->newLine();
            $this->info('File Age Information:');
            $this->line("Oldest file: {$stats['oldest_file']->format('Y-m-d H:i:s')} ({$stats['oldest_file']->diffForHumans()})");
            $this->line("Newest file: {$stats['newest_file']->format('Y-m-d H:i:s')} ({$stats['newest_file']->diffForHumans()})");
        }

        // Certificate subdirectories
        if (! empty($stats['certificates']['subdirectories'])) {
            $this->newLine();
            $this->info('Certificate Files by Month:');

            $subdirData = [];
            foreach ($stats['certificates']['subdirectories'] as $subdir => $subdirStats) {
                $subdirData[] = [$subdir, $subdirStats['file_count'], $subdirStats['size_mb']];
            }

            $this->table(['Month', 'Files', 'Size (MB)'], $subdirData);
        }

        // Storage recommendations
        $recommendations = $storageService->getStorageRecommendations();
        if (! empty($recommendations)) {
            $this->newLine();
            $this->info('Storage Recommendations:');
            foreach ($recommendations as $rec) {
                $icon = $rec['type'] === 'warning' ? '⚠️' : ($rec['type'] === 'info' ? 'ℹ️' : '💡');
                $this->line("{$icon} {$rec['title']}: {$rec['message']}");
            }
        }

        $this->newLine();
    }

    /**
     * Simulate cleanup without actually deleting files
     */
    private function simulateCleanup(CertificateStorageService $storageService, int $daysToKeep, string $cleanupType): void
    {
        $this->warn('DRY RUN SIMULATION - Estimating what would be cleaned...');
        $this->newLine();

        // Get validation results to show potentially problematic files
        if (in_array($cleanupType, ['certificates', 'all'])) {
            $this->info('Checking certificate file integrity...');
            $validation = $storageService->validateCertificateFiles();

            if ($validation['missing_files'] > 0 || $validation['corrupted_files'] > 0) {
                $this->warn('Found potential issues:');
                $this->line("- Missing files: {$validation['missing_files']}");
                $this->line("- Corrupted files: {$validation['corrupted_files']}");
                $this->line("- Invalid references: {$validation['invalid_references']}");
            }
        }

        // Check for orphaned files
        if (in_array($cleanupType, ['certificates', 'all'])) {
            $this->info('Checking for orphaned certificate files...');
            $orphanedResults = $storageService->cleanupOrphanedFiles();

            if ($orphanedResults['orphaned_files_found'] > 0) {
                $this->warn("Would clean {$orphanedResults['orphaned_files_found']} orphaned files");
                $this->line("Estimated space to free: {$orphanedResults['size_freed_mb']} MB");
            } else {
                $this->info('No orphaned files found.');
            }
        }

        $this->newLine();
        $this->info('To perform actual cleanup, run without --dry-run flag');
        $this->info('To dispatch as background job, run: php artisan certificates:cleanup --days='.$daysToKeep.' --type='.$cleanupType);
    }

    /**
     * Dispatch cleanup job
     */
    private function dispatchCleanupJob(int $daysToKeep, string $cleanupType, bool $force): void
    {
        try {
            CleanupOldCertificatesJob::dispatch($daysToKeep, $cleanupType, $force);

            $this->info('✅ Cleanup job dispatched successfully!');
            $this->info('The cleanup will run in the background.');
            $this->info('Check the job progress using: php artisan queue:work');
            $this->newLine();

            $this->comment('You can monitor the cleanup progress in the admin panel under Job Progress.');

        } catch (\Exception $e) {
            $this->error('❌ Failed to dispatch cleanup job: '.$e->getMessage());
            Log::error('Certificate cleanup command failed', [
                'error' => $e->getMessage(),
                'days_to_keep' => $daysToKeep,
                'cleanup_type' => $cleanupType,
                'force' => $force,
            ]);

            return;
        }
    }
}
