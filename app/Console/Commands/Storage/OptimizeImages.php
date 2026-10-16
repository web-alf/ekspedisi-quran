<?php

namespace App\Console\Commands\Storage;

use App\Services\FileStorageService;
use App\Services\ImageOptimizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OptimizeImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:optimize-images 
                            {directory? : Specific directory to optimize}
                            {--existing : Optimize existing images}
                            {--convert-webp : Convert images to WebP format}
                            {--quality=85 : Image quality (0-100)}
                            {--max-width=1920 : Maximum width}
                            {--max-height=1080 : Maximum height}
                            {--dry-run : Show what would be optimized without processing}
                            {--force : Skip confirmation prompts}
                            {--batch-size=10 : Number of images to process per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize existing images to reduce storage usage';

    protected ImageOptimizationService $imageService;

    protected FileStorageService $fileStorageService;

    public function __construct(ImageOptimizationService $imageService, FileStorageService $fileStorageService)
    {
        parent::__construct();
        $this->imageService = $imageService;
        $this->fileStorageService = $fileStorageService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $directory = $this->argument('directory');
        $optimizeExisting = $this->option('existing');
        $convertWebp = $this->option('convert-webp');
        $quality = (int) $this->option('quality');
        $maxWidth = (int) $this->option('max-width');
        $maxHeight = (int) $this->option('max-height');
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');
        $batchSize = (int) $this->option('batch-size');

        $this->info('🖼️  Image Optimization Process');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No files will be modified');
        }

        // Find images to optimize
        $imagesToOptimize = $this->findImagesToOptimize($directory);

        if (empty($imagesToOptimize)) {
            $this->info('✨ No images found that need optimization!');

            return self::SUCCESS;
        }

        $this->info('📋 Found '.count($imagesToOptimize).' images to process');

        // Show optimization settings
        $this->displayOptimizationSettings($quality, $maxWidth, $maxHeight, $convertWebp);

        // Group images by type for better reporting
        $imagesByType = $this->groupImagesByType($imagesToOptimize);
        $this->displayImagesSummary($imagesByType);

        // Confirmation for non-dry-run operations
        if (! $isDryRun && ! $force) {
            if (! $this->confirm('⚠️  Proceed with image optimization?')) {
                $this->info('Optimization cancelled.');

                return self::SUCCESS;
            }
        }

        // Process images in batches
        $results = $this->processImagesInBatches(
            $imagesToOptimize,
            $batchSize,
            $quality,
            $maxWidth,
            $maxHeight,
            $convertWebp,
            $isDryRun
        );

        // Display results
        $this->displayOptimizationResults($results, $isDryRun);

        return self::SUCCESS;
    }

    protected function findImagesToOptimize(?string $directory = null): array
    {
        $disk = Storage::disk('public');
        $images = [];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];

        $searchDirectories = $directory ? [$directory] : [''];

        foreach ($searchDirectories as $searchDir) {
            try {
                $files = $this->getAllFilesRecursive($disk, $searchDir);

                foreach ($files as $file) {
                    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

                    if (in_array($extension, $imageExtensions)) {
                        // Skip thumbnails and already optimized files
                        if (! str_contains($file, '/thumbnails/') &&
                            ! str_contains($file, '_optimized') &&
                            $extension !== 'webp') {

                            $images[] = [
                                'path' => $file,
                                'extension' => $extension,
                                'size' => $disk->size($file),
                                'last_modified' => $disk->lastModified($file),
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->warn("Could not read directory {$searchDir}: ".$e->getMessage());
            }
        }

        // Sort by size (largest first) for better optimization impact
        usort($images, fn ($a, $b) => $b['size'] - $a['size']);

        return $images;
    }

    protected function getAllFilesRecursive($disk, string $directory): array
    {
        $allFiles = [];
        $directories = [$directory];

        while (! empty($directories)) {
            $currentDir = array_shift($directories);

            try {
                $files = $disk->files($currentDir);
                $allFiles = array_merge($allFiles, $files);

                $subdirectories = $disk->directories($currentDir);
                $directories = array_merge($directories, $subdirectories);
            } catch (\Exception $e) {
                continue;
            }
        }

        return $allFiles;
    }

    protected function groupImagesByType(array $images): array
    {
        $grouped = [];

        foreach ($images as $image) {
            $type = $image['extension'];
            if (! isset($grouped[$type])) {
                $grouped[$type] = ['count' => 0, 'total_size' => 0];
            }
            $grouped[$type]['count']++;
            $grouped[$type]['total_size'] += $image['size'];
        }

        return $grouped;
    }

    protected function displayOptimizationSettings(int $quality, int $maxWidth, int $maxHeight, bool $convertWebp): void
    {
        $this->info("\n⚙️  Optimization Settings:");
        $this->table(['Setting', 'Value'], [
            ['Quality', $quality.'%'],
            ['Max Width', $maxWidth.'px'],
            ['Max Height', $maxHeight.'px'],
            ['Convert to WebP', $convertWebp ? '✅ Yes' : '❌ No'],
        ]);
    }

    protected function displayImagesSummary(array $imagesByType): void
    {
        $this->info("\n📊 Images by Type:");

        $summaryData = [];
        $totalCount = 0;
        $totalSize = 0;

        foreach ($imagesByType as $type => $stats) {
            $summaryData[] = [
                strtoupper($type),
                number_format($stats['count']),
                $this->formatFileSize($stats['total_size']),
            ];
            $totalCount += $stats['count'];
            $totalSize += $stats['total_size'];
        }

        $summaryData[] = [
            'TOTAL',
            number_format($totalCount),
            $this->formatFileSize($totalSize),
        ];

        $this->table(['Type', 'Count', 'Total Size'], $summaryData);
    }

    protected function processImagesInBatches(
        array $images,
        int $batchSize,
        int $quality,
        int $maxWidth,
        int $maxHeight,
        bool $convertWebp,
        bool $isDryRun
    ): array {
        $results = [
            'processed' => 0,
            'errors' => 0,
            'total_size_before' => 0,
            'total_size_after' => 0,
            'processing_time' => 0,
            'details' => [],
        ];

        $progressBar = $this->output->createProgressBar(count($images));
        $progressBar->setFormat('Processing: %current%/%max% [%bar%] %percent:3s%% - %message%');
        $progressBar->setMessage('Starting...');
        $progressBar->start();

        $batches = array_chunk($images, $batchSize);
        $startTime = microtime(true);

        foreach ($batches as $batchIndex => $batch) {
            foreach ($batch as $image) {
                $progressBar->setMessage('Processing: '.basename($image['path']));

                try {
                    if (! $isDryRun) {
                        $result = $this->optimizeImage(
                            $image['path'],
                            $quality,
                            $maxWidth,
                            $maxHeight,
                            $convertWebp
                        );

                        $results['total_size_before'] += $image['size'];
                        $results['total_size_after'] += $result['optimized_size'] ?? $image['size'];
                        $results['details'][] = $result;
                    } else {
                        // Dry run - estimate savings
                        $estimatedReduction = $this->estimateCompression($image['size'], $image['extension']);
                        $results['total_size_before'] += $image['size'];
                        $results['total_size_after'] += $image['size'] - $estimatedReduction;

                        $results['details'][] = [
                            'path' => $image['path'],
                            'original_size' => $image['size'],
                            'estimated_size' => $image['size'] - $estimatedReduction,
                            'estimated_savings' => $estimatedReduction,
                            'status' => 'dry_run',
                        ];
                    }

                    $results['processed']++;

                } catch (\Exception $e) {
                    $results['errors']++;
                    $results['details'][] = [
                        'path' => $image['path'],
                        'error' => $e->getMessage(),
                        'status' => 'error',
                    ];

                    if ($this->option('verbose')) {
                        $this->newLine();
                        $this->error("Error processing {$image['path']}: ".$e->getMessage());
                    }
                }

                $progressBar->advance();
            }

            // Small delay between batches to prevent memory issues
            if ($batchIndex < count($batches) - 1) {
                usleep(100000); // 0.1 second
            }
        }

        $results['processing_time'] = microtime(true) - $startTime;
        $progressBar->finish();
        $this->newLine(2);

        return $results;
    }

    protected function optimizeImage(string $path, int $quality, int $maxWidth, int $maxHeight, bool $convertWebp): array
    {
        $options = [
            'webp_quality' => $quality,
            'max_width' => $maxWidth,
            'max_height' => $maxHeight,
            'preserve_aspect_ratio' => true,
        ];

        if ($convertWebp) {
            return $this->imageService->convertToWebP($path, null, $quality);
        } else {
            return $this->imageService->optimizeExisting($path, $options);
        }
    }

    protected function estimateCompression(int $originalSize, string $extension): int
    {
        // Rough estimates based on typical compression ratios
        $compressionRates = [
            'jpg' => 0.15,  // 15% reduction
            'jpeg' => 0.15,
            'png' => 0.30,  // 30% reduction (PNG compresses better)
            'gif' => 0.10,  // 10% reduction
            'bmp' => 0.80,  // 80% reduction (BMPs are huge)
        ];

        $rate = $compressionRates[$extension] ?? 0.20; // Default 20%

        return (int) ($originalSize * $rate);
    }

    protected function displayOptimizationResults(array $results, bool $isDryRun): void
    {
        $this->info("\n🎯 Optimization Results:");

        $action = $isDryRun ? 'would be' : 'were';
        $sizeBefore = $results['total_size_before'];
        $sizeAfter = $results['total_size_after'];
        $sizeSaved = $sizeBefore - $sizeAfter;
        $compressionRatio = $sizeBefore > 0 ? round(($sizeSaved / $sizeBefore) * 100, 2) : 0;

        // Summary table
        $this->table(['Metric', 'Value'], [
            ['Images processed', number_format($results['processed'])],
            ['Processing errors', number_format($results['errors'])],
            ['Processing time', round($results['processing_time'], 2).' seconds'],
            ['Size before', $this->formatFileSize($sizeBefore)],
            ['Size after', $this->formatFileSize($sizeAfter)],
            ['Space '.$action.' saved', $this->formatFileSize($sizeSaved)],
            ['Compression ratio', $compressionRatio.'%'],
        ]);

        // Show top savings if verbose
        if ($this->option('verbose') && ! empty($results['details'])) {
            $this->info("\n📋 Top 10 Optimizations:");

            // Sort by savings (descending)
            $topSavings = array_filter($results['details'], fn ($detail) => isset($detail['original_size']));
            usort($topSavings, function ($a, $b) {
                $savingsA = ($a['original_size'] ?? 0) - ($a['optimized_size'] ?? $a['estimated_size'] ?? 0);
                $savingsB = ($b['original_size'] ?? 0) - ($b['optimized_size'] ?? $b['estimated_size'] ?? 0);

                return $savingsB - $savingsA;
            });

            $topSavings = array_slice($topSavings, 0, 10);

            foreach ($topSavings as $detail) {
                $originalSize = $detail['original_size'] ?? 0;
                $newSize = $detail['optimized_size'] ?? $detail['estimated_size'] ?? 0;
                $savings = $originalSize - $newSize;
                $ratio = $originalSize > 0 ? round(($savings / $originalSize) * 100, 1) : 0;

                $this->line('  • '.basename($detail['path']).
                          " - {$this->formatFileSize($savings)} saved ({$ratio}%)");
            }
        }

        // Show errors if any
        if ($results['errors'] > 0) {
            $errorDetails = array_filter($results['details'], fn ($detail) => isset($detail['error']));
            $this->warn("\n⚠️  Errors encountered:");
            foreach ($errorDetails as $error) {
                $this->line('  • '.basename($error['path']).': '.$error['error']);
            }
        }

        if (! $isDryRun && $results['processed'] > 0) {
            $this->info("\n✅ Image optimization completed successfully!");
            $this->info("💡 You can run 'php artisan storage:cleanup' to remove any leftover files.");
        } elseif ($isDryRun) {
            $this->warn("\n👀 This was a dry run. Add --existing flag to actually optimize images.");
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
