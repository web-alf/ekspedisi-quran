<?php

namespace App\Console\Commands;

use App\Services\ImageOptimizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class OptimizeImages extends Command
{
    protected $signature = 'images:optimize 
                            {--directory= : Direktori yang akan dioptimasi (galleries, testimonials, dll)}
                            {--model= : Model yang akan dioptimasi (Gallery, Testimonial, dll)}
                            {--quality=85 : Kualitas WebP (0-100)}
                            {--max-width=1920 : Lebar maksimum}
                            {--max-height=1080 : Tinggi maksimum}
                            {--backup : Backup file original sebelum optimasi}
                            {--force : Optimasi file yang sudah dalam format WebP}
                            {--dry-run : Hanya tampilkan file yang akan dioptimasi tanpa melakukan perubahan}';

    protected $description = 'Optimasi gambar yang sudah ada menjadi WebP dan kompres';

    protected ImageOptimizationService $imageService;

    public function __construct(ImageOptimizationService $imageService)
    {
        parent::__construct();
        $this->imageService = $imageService;
    }

    public function handle()
    {
        $this->info('🚀 Memulai optimasi gambar...');
        
        $directory = $this->option('directory');
        $model = $this->option('model');
        $dryRun = $this->option('dry-run');
        
        if ($model) {
            $this->optimizeByModel($model, $dryRun);
        } elseif ($directory) {
            $this->optimizeByDirectory($directory, $dryRun);
        } else {
            $this->optimizeAll($dryRun);
        }
        
        $this->info('✅ Optimasi selesai!');
    }

    /**
     * Optimasi berdasarkan model
     */
    protected function optimizeByModel(string $modelName, bool $dryRun = false): void
    {
        $modelClass = "App\\Models\\{$modelName}";
        
        if (!class_exists($modelClass)) {
            $this->error("Model {$modelName} tidak ditemukan!");
            return;
        }

        $this->info("📁 Optimasi gambar untuk model: {$modelName}");
        
        $records = $modelClass::whereNotNull('image')->get();
        $this->optimizeRecords($records, $dryRun);
    }

    /**
     * Optimasi berdasarkan direktori
     */
    protected function optimizeByDirectory(string $directory, bool $dryRun = false): void
    {
        $this->info("📁 Optimasi gambar di direktori: {$directory}");
        
        $files = Storage::disk('public')->allFiles($directory);
        $imageFiles = array_filter($files, [$this, 'isImageFile']);
        
        $this->optimizeFiles($imageFiles, $dryRun);
    }

    /**
     * Optimasi semua gambar
     */
    protected function optimizeAll(bool $dryRun = false): void
    {
        $this->info("📁 Optimasi semua gambar...");
        
        // Optimasi berdasarkan model yang umum digunakan
        $models = ['Gallery', 'Testimonial'];
        
        foreach ($models as $model) {
            if (class_exists("App\\Models\\{$model}")) {
                $this->optimizeByModel($model, $dryRun);
            }
        }
        
        // Optimasi direktori umum
        $directories = ['uploads', 'settings', 'certificates'];
        
        foreach ($directories as $dir) {
            if (Storage::disk('public')->exists($dir)) {
                $this->optimizeByDirectory($dir, $dryRun);
            }
        }
    }

    /**
     * Optimasi records dari database
     */
    protected function optimizeRecords($records, bool $dryRun = false): void
    {
        $total = $records->count();
        $processed = 0;
        $optimized = 0;
        $errors = 0;
        
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();
        
        foreach ($records as $record) {
            try {
                $result = $this->optimizeSingleRecord($record, $dryRun);
                
                if ($result['optimized']) {
                    $optimized++;
                    
                    if (!$dryRun) {
                        $this->updateDatabaseRecord($record, $result['new_path']);
                    }
                }
                
                $processed++;
                
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("❌ Error pada {$record->id}: " . $e->getMessage());
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        
        $this->displaySummary($total, $processed, $optimized, $errors, $dryRun);
    }

    /**
     * Optimasi files langsung
     */
    protected function optimizeFiles(array $files, bool $dryRun = false): void
    {
        $total = count($files);
        $processed = 0;
        $optimized = 0;
        $errors = 0;
        
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();
        
        foreach ($files as $file) {
            try {
                $result = $this->optimizeSingleFile($file, $dryRun);
                
                if ($result['optimized']) {
                    $optimized++;
                }
                
                $processed++;
                
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("❌ Error pada {$file}: " . $e->getMessage());
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        
        $this->displaySummary($total, $processed, $optimized, $errors, $dryRun);
    }

    /**
     * Optimasi single record
     */
    protected function optimizeSingleRecord($record, bool $dryRun = false): array
    {
        $imagePath = $record->image;
        
        if (!$imagePath || !Storage::disk('public')->exists($imagePath)) {
            return ['optimized' => false, 'reason' => 'File tidak ditemukan'];
        }
        
        // Skip jika sudah WebP dan tidak force
        if ($this->isWebP($imagePath) && !$this->option('force')) {
            return ['optimized' => false, 'reason' => 'Sudah WebP'];
        }
        
        if ($dryRun) {
            return ['optimized' => true, 'reason' => 'Dry run'];
        }
        
        // Backup jika diminta
        if ($this->option('backup')) {
            $this->backupFile($imagePath);
        }
        
        // Optimasi
        $config = $this->getOptimizationConfig();
        $result = $this->imageService->optimizeExisting($imagePath, $config);
        
        return [
            'optimized' => true,
            'new_path' => $result['optimized_path'],
            'original_size' => $result['original_size'],
            'optimized_size' => $result['optimized_size'],
            'compression_ratio' => $result['compression_ratio']
        ];
    }

    /**
     * Optimasi single file
     */
    protected function optimizeSingleFile(string $filePath, bool $dryRun = false): array
    {
        if (!Storage::disk('public')->exists($filePath)) {
            return ['optimized' => false, 'reason' => 'File tidak ditemukan'];
        }
        
        // Skip jika sudah WebP dan tidak force
        if ($this->isWebP($filePath) && !$this->option('force')) {
            return ['optimized' => false, 'reason' => 'Sudah WebP'];
        }
        
        if ($dryRun) {
            return ['optimized' => true, 'reason' => 'Dry run'];
        }
        
        // Backup jika diminta
        if ($this->option('backup')) {
            $this->backupFile($filePath);
        }
        
        // Optimasi
        $config = $this->getOptimizationConfig();
        $result = $this->imageService->optimizeExisting($filePath, $config);
        
        return [
            'optimized' => true,
            'original_size' => $result['original_size'],
            'optimized_size' => $result['optimized_size'],
            'compression_ratio' => $result['compression_ratio']
        ];
    }

    /**
     * Update database record dengan path baru
     */
    protected function updateDatabaseRecord($record, string $newPath): void
    {
        $record->update(['image' => $newPath]);
    }

    /**
     * Backup file original
     */
    protected function backupFile(string $filePath): void
    {
        $backupPath = 'backups/' . $filePath;
        Storage::disk('public')->copy($filePath, $backupPath);
    }

    /**
     * Cek apakah file adalah gambar
     */
    protected function isImageFile(string $filePath): bool
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        return in_array($extension, $allowedExtensions);
    }

    /**
     * Cek apakah file adalah WebP
     */
    protected function isWebP(string $filePath): bool
    {
        return strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'webp';
    }

    /**
     * Get konfigurasi optimasi dari options
     */
    protected function getOptimizationConfig(): array
    {
        return [
            'webp_quality' => (int) $this->option('quality'),
            'max_width' => (int) $this->option('max-width'),
            'max_height' => (int) $this->option('max-height'),
            'create_thumbnail' => true,
        ];
    }

    /**
     * Tampilkan summary hasil optimasi
     */
    protected function displaySummary(int $total, int $processed, int $optimized, int $errors, bool $dryRun): void
    {
        $this->newLine();
        $this->info("📊 Summary:");
        $this->line("   Total files: {$total}");
        $this->line("   Processed: {$processed}");
        $this->line("   Optimized: {$optimized}");
        
        if ($errors > 0) {
            $this->line("   Errors: {$errors}");
        }
        
        if ($dryRun) {
            $this->warn("   🔍 Ini adalah dry run - tidak ada perubahan yang dibuat");
            $this->info("   Jalankan tanpa --dry-run untuk melakukan optimasi sebenarnya");
        }
        
        $percentage = $total > 0 ? round(($optimized / $total) * 100, 2) : 0;
        $this->info("   Success rate: {$percentage}%");
    }
}
