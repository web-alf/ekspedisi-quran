<?php

namespace App\Console\Commands\Storage;

use App\Services\FileStorageService;
use App\Services\StorageMonitoringService;
use Illuminate\Console\Command;

class CleanupFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:cleanup 
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--orphaned : Only clean up orphaned files}
                            {--thumbnails : Only clean up old thumbnails}
                            {--temp : Only clean up temporary files}
                            {--days=30 : Age in days for files to be considered for cleanup}
                            {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned, temporary, and old files from storage';

    protected FileStorageService $fileStorageService;

    protected StorageMonitoringService $monitoringService;

    public function __construct(FileStorageService $fileStorageService, StorageMonitoringService $monitoringService)
    {
        parent::__construct();
        $this->fileStorageService = $fileStorageService;
        $this->monitoringService = $monitoringService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $orphanedOnly = $this->option('orphaned');
        $thumbnailsOnly = $this->option('thumbnails');
        $tempOnly = $this->option('temp');
        $days = (int) $this->option('days');
        $force = $this->option('force');

        $this->info('🧹 Starting file cleanup process...');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No files will be deleted');
        }

        // Show current storage stats
        $this->showStorageStats();

        $options = [
            'remove_orphaned' => ! $thumbnailsOnly && ! $tempOnly,
            'remove_old_thumbnails' => ! $orphanedOnly && ! $tempOnly,
            'remove_temp_files' => ! $orphanedOnly && ! $thumbnailsOnly,
            'orphan_age_days' => $days,
            'dry_run' => $isDryRun,
        ];

        if ($orphanedOnly) {
            $options['remove_old_thumbnails'] = false;
            $options['remove_temp_files'] = false;
        } elseif ($thumbnailsOnly) {
            $options['remove_orphaned'] = false;
            $options['remove_temp_files'] = false;
        } elseif ($tempOnly) {
            $options['remove_orphaned'] = false;
            $options['remove_old_thumbnails'] = false;
        }

        // Show what will be cleaned
        $this->info("\n📋 Cleanup configuration:");
        $this->table(['Setting', 'Value'], [
            ['Remove orphaned files', $options['remove_orphaned'] ? '✅ Yes' : '❌ No'],
            ['Remove old thumbnails', $options['remove_old_thumbnails'] ? '✅ Yes' : '❌ No'],
            ['Remove temp files', $options['remove_temp_files'] ? '✅ Yes' : '❌ No'],
            ['Minimum age (days)', $days],
            ['Dry run mode', $isDryRun ? '✅ Yes' : '❌ No'],
        ]);

        // Confirmation for non-dry-run operations
        if (! $isDryRun && ! $force) {
            if (! $this->confirm('⚠️  Are you sure you want to proceed with file cleanup?')) {
                $this->info('Cleanup cancelled.');

                return self::SUCCESS;
            }
        }

        // Perform cleanup
        $results = $this->fileStorageService->cleanupFiles($options);

        // Display results
        $this->displayCleanupResults($results, $isDryRun);

        // Show updated storage stats
        if (! $isDryRun && $results['total_files_removed'] > 0) {
            $this->info("\n📊 Updated storage statistics:");
            $this->showStorageStats();
        }

        return self::SUCCESS;
    }

    protected function showStorageStats(): void
    {
        $stats = $this->fileStorageService->getStorageStats();

        $this->table(['Metric', 'Value'], [
            ['Total files', number_format($stats['file_count'])],
            ['Total size', $stats['total_size_formatted']],
            ['Storage usage', $stats['usage_percentage'].'%'],
            ['Available space', $stats['available_space_formatted']],
        ]);
    }

    protected function displayCleanupResults(array $results, bool $isDryRun): void
    {
        $this->info("\n🎯 Cleanup Results:");

        if ($results['total_files_removed'] === 0) {
            $this->info('✨ No files needed cleanup!');

            return;
        }

        $action = $isDryRun ? 'would be' : 'were';

        // Summary table
        $summaryData = [
            ['Type', 'Files '.$action.' removed', 'Space '.$action.' freed'],
        ];

        if (! empty($results['orphaned_files'])) {
            $summaryData[] = [
                '🗑️  Orphaned files',
                count($results['orphaned_files']),
                $this->formatFileSize(array_sum(array_column($results['orphaned_files'], 'size'))),
            ];
        }

        if (! empty($results['old_thumbnails'])) {
            $summaryData[] = [
                '🖼️  Old thumbnails',
                count($results['old_thumbnails']),
                $this->formatFileSize(array_sum(array_column($results['old_thumbnails'], 'size'))),
            ];
        }

        if (! empty($results['temp_files'])) {
            $summaryData[] = [
                '📁 Temp files',
                count($results['temp_files']),
                $this->formatFileSize(array_sum(array_column($results['temp_files'], 'size'))),
            ];
        }

        $summaryData[] = [
            '📊 TOTAL',
            $results['total_files_removed'],
            $results['total_size_freed_formatted'],
        ];

        $this->table($summaryData[0], array_slice($summaryData, 1));

        // Detailed list if verbose
        if ($this->option('verbose') && $results['total_files_removed'] > 0) {
            $this->info("\n📝 Detailed file list:");

            foreach (['orphaned_files', 'old_thumbnails', 'temp_files'] as $type) {
                if (! empty($results[$type])) {
                    $this->info("\n".ucfirst(str_replace('_', ' ', $type)).':');
                    foreach ($results[$type] as $file) {
                        $this->line("  • {$file['path']} ({$this->formatFileSize($file['size'])})");
                    }
                }
            }
        }

        if (! $isDryRun) {
            $this->info("\n✅ Cleanup completed successfully!");
        } else {
            $this->warn("\n👀 This was a dry run. Run without --dry-run to actually delete files.");
        }
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
