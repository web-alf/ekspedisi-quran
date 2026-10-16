<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageOptimizationService
{
    protected ImageManager $manager;

    // Configuration untuk kualitas dan ukuran
    protected array $config = [
        'webp_quality' => 85,           // Kualitas WebP (0-100)
        'jpeg_quality' => 85,           // Kualitas JPEG fallback (0-100)
        'max_width' => 1920,            // Lebar maksimum
        'max_height' => 1080,           // Tinggi maksimum
        'thumbnail_width' => 300,       // Lebar thumbnail
        'thumbnail_height' => 200,      // Tinggi thumbnail
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'create_thumbnail' => true,     // Buat thumbnail otomatis
        'preserve_aspect_ratio' => true, // Jaga aspek rasio
    ];

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);

        // Load config dari file jika ada
        if (config('image_optimization')) {
            $defaultConfig = config('image_optimization.directories.default', []);
            $this->config = array_merge($this->config, $defaultConfig);
        }
    }

    /**
     * Optimasi gambar yang diupload
     */
    public function optimizeAndStore(UploadedFile $file, string $directory = 'images', array $options = []): array
    {
        // Merge dengan konfigurasi default
        $config = array_merge($this->config, $options);

        // Validasi file
        $this->validateFile($file, $config['allowed_types']);

        // Generate nama file unik
        $fileName = $this->generateFileName($file);
        $webpFileName = $this->changeExtension($fileName, 'webp');
        $thumbnailFileName = 'thumb_'.$webpFileName;

        // Path untuk menyimpan
        $mainPath = $directory.'/'.$webpFileName;
        $thumbnailPath = $directory.'/thumbnails/'.$thumbnailFileName;

        // Proses gambar utama
        $image = $this->manager->read($file->getPathname());

        // Resize jika perlu
        if ($config['max_width'] || $config['max_height']) {
            $image = $this->resizeImage($image, $config['max_width'], $config['max_height'], $config['preserve_aspect_ratio']);
        }

        // Simpan gambar utama sebagai WebP
        $webpData = $image->toWebp($config['webp_quality']);
        Storage::disk('public')->put($mainPath, $webpData);

        $result = [
            'original_name' => $file->getClientOriginalName(),
            'file_name' => $webpFileName,
            'path' => $mainPath,
            'size' => Storage::disk('public')->size($mainPath),
            'mime_type' => 'image/webp',
            'dimensions' => [
                'width' => $image->width(),
                'height' => $image->height(),
            ],
        ];

        // Buat thumbnail jika diminta
        if ($config['create_thumbnail']) {
            $thumbnail = $this->createThumbnail(
                $image,
                $config['thumbnail_width'],
                $config['thumbnail_height']
            );

            $thumbnailData = $thumbnail->toWebp($config['webp_quality']);
            Storage::disk('public')->put($thumbnailPath, $thumbnailData);

            $result['thumbnail'] = [
                'path' => $thumbnailPath,
                'size' => Storage::disk('public')->size($thumbnailPath),
                'dimensions' => [
                    'width' => $thumbnail->width(),
                    'height' => $thumbnail->height(),
                ],
            ];
        }

        // Log optimization activity
        $this->logOptimization([
            'original_name' => $file->getClientOriginalName(),
            'optimized_path' => $mainPath,
            'original_size' => $file->getSize(),
            'optimized_size' => $result['size'],
            'compression_ratio' => round((1 - $result['size'] / $file->getSize()) * 100, 2).'%',
            'directory' => $directory,
        ]);

        // Cleanup memory (v3+ handles this automatically)
        unset($image);
        if (isset($thumbnail)) {
            unset($thumbnail);
        }

        return $result;
    }

    /**
     * Batch optimization untuk multiple files
     */
    public function optimizeBatch(array $files, string $directory = 'images', array $options = []): array
    {
        $results = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $results[] = $this->optimizeAndStore($file, $directory, $options);
            }
        }

        return $results;
    }

    /**
     * Validasi file upload
     */
    protected function validateFile(UploadedFile $file, array $allowedTypes): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, $allowedTypes)) {
            throw new \InvalidArgumentException("File type '{$extension}' is not allowed. Allowed types: ".implode(', ', $allowedTypes));
        }

        if (! $file->isValid()) {
            throw new \InvalidArgumentException('Invalid file upload');
        }
    }

    /**
     * Generate nama file unik
     */
    protected function generateFileName(UploadedFile $file): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = Str::slug($name);
        $name = Str::limit($name, 50, '');

        return $name.'_'.time().'_'.Str::random(8);
    }

    /**
     * Ubah ekstensi file
     */
    protected function changeExtension(string $filename, string $newExtension): string
    {
        return pathinfo($filename, PATHINFO_FILENAME).'.'.$newExtension;
    }

    /**
     * Resize gambar dengan mempertahankan aspek rasio
     */
    protected function resizeImage($image, ?int $maxWidth, ?int $maxHeight, bool $preserveAspectRatio = true)
    {
        $currentWidth = $image->width();
        $currentHeight = $image->height();

        // Skip jika gambar sudah kecil
        if ((! $maxWidth || $currentWidth <= $maxWidth) &&
            (! $maxHeight || $currentHeight <= $maxHeight)) {
            return $image;
        }

        if ($preserveAspectRatio) {
            // Hitung rasio untuk fit dalam batas maksimum
            $ratioW = $maxWidth ? $maxWidth / $currentWidth : 1;
            $ratioH = $maxHeight ? $maxHeight / $currentHeight : 1;
            $ratio = min($ratioW, $ratioH);

            $newWidth = (int) ($currentWidth * $ratio);
            $newHeight = (int) ($currentHeight * $ratio);
        } else {
            $newWidth = $maxWidth ?: $currentWidth;
            $newHeight = $maxHeight ?: $currentHeight;
        }

        return $image->resize($newWidth, $newHeight);
    }

    /**
     * Buat thumbnail
     */
    protected function createThumbnail($image, int $width, int $height)
    {
        return $image->cover($width, $height);
    }

    /**
     * Optimasi gambar yang sudah ada di storage
     */
    public function optimizeExisting(string $path, array $options = []): array
    {
        if (! Storage::disk('public')->exists($path)) {
            throw new \InvalidArgumentException("File not found: {$path}");
        }

        $config = array_merge($this->config, $options);
        $fullPath = Storage::disk('public')->path($path);

        // Baca gambar existing
        $image = $this->manager->read($fullPath);

        // Generate nama file baru dengan ekstensi webp
        $pathInfo = pathinfo($path);
        $directory = $pathInfo['dirname'];
        $fileName = $pathInfo['filename'].'_optimized';
        $webpPath = $directory.'/'.$fileName.'.webp';

        // Resize jika perlu
        if ($config['max_width'] || $config['max_height']) {
            $image = $this->resizeImage($image, $config['max_width'], $config['max_height'], $config['preserve_aspect_ratio']);
        }

        // Simpan sebagai WebP
        $webpData = $image->toWebp($config['webp_quality']);
        Storage::disk('public')->put($webpPath, $webpData);

        $result = [
            'original_path' => $path,
            'optimized_path' => $webpPath,
            'original_size' => Storage::disk('public')->size($path),
            'optimized_size' => Storage::disk('public')->size($webpPath),
            'compression_ratio' => round((1 - Storage::disk('public')->size($webpPath) / Storage::disk('public')->size($path)) * 100, 2).'%',
        ];

        unset($image);

        return $result;
    }

    /**
     * Get konfigurasi
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set konfigurasi
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * Calculate compression ratio and storage savings
     */
    public function calculateCompressionStats(string $originalPath, string $optimizedPath): array
    {
        $originalSize = Storage::disk('public')->size($originalPath);
        $optimizedSize = Storage::disk('public')->size($optimizedPath);

        $compressionRatio = $originalSize > 0 ? round((1 - $optimizedSize / $originalSize) * 100, 2) : 0;
        $spaceSaved = $originalSize - $optimizedSize;

        return [
            'original_size' => $originalSize,
            'optimized_size' => $optimizedSize,
            'compression_ratio' => $compressionRatio,
            'space_saved' => $spaceSaved,
            'space_saved_formatted' => $this->formatFileSize($spaceSaved),
        ];
    }

    /**
     * Progressive JPEG support for better loading
     */
    public function createProgressiveJpeg($image, int $quality = 85): string
    {
        return $image->toJpeg($quality)->toDataUrl();
    }

    /**
     * Create multiple sizes for responsive images
     */
    public function createResponsiveSizes(UploadedFile $file, string $directory = 'images', array $sizes = []): array
    {
        $defaultSizes = [
            'small' => ['width' => 400, 'height' => 300],
            'medium' => ['width' => 800, 'height' => 600],
            'large' => ['width' => 1200, 'height' => 900],
            'xlarge' => ['width' => 1920, 'height' => 1080],
        ];

        $sizes = array_merge($defaultSizes, $sizes);
        $results = [];

        $image = $this->manager->read($file->getPathname());
        $baseFileName = $this->generateFileName($file);

        foreach ($sizes as $sizeName => $dimensions) {
            $fileName = $baseFileName.'_'.$sizeName.'.webp';
            $path = $directory.'/'.$fileName;

            $resizedImage = $this->resizeImage(
                clone $image,
                $dimensions['width'],
                $dimensions['height'],
                true
            );

            $webpData = $resizedImage->toWebp($this->config['webp_quality']);
            Storage::disk('public')->put($path, $webpData);

            $results[$sizeName] = [
                'path' => $path,
                'size' => Storage::disk('public')->size($path),
                'dimensions' => [
                    'width' => $resizedImage->width(),
                    'height' => $resizedImage->height(),
                ],
            ];

            unset($resizedImage);
        }

        unset($image);

        return $results;
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
     * Convert image to WebP with fallback
     */
    public function convertToWebP(string $sourcePath, ?string $targetPath = null, int $quality = 85): array
    {
        if (! Storage::disk('public')->exists($sourcePath)) {
            throw new \InvalidArgumentException("Source file not found: {$sourcePath}");
        }

        $targetPath = $targetPath ?? $this->generateWebPPath($sourcePath);
        $fullSourcePath = Storage::disk('public')->path($sourcePath);

        $image = $this->manager->read($fullSourcePath);
        $webpData = $image->toWebp($quality);

        Storage::disk('public')->put($targetPath, $webpData);

        $stats = $this->calculateCompressionStats($sourcePath, $targetPath);

        unset($image);

        return array_merge($stats, [
            'source_path' => $sourcePath,
            'target_path' => $targetPath,
            'conversion_successful' => true,
        ]);
    }

    /**
     * Generate WebP file path from original path
     */
    protected function generateWebPPath(string $originalPath): string
    {
        $pathInfo = pathinfo($originalPath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];

        return $directory.'/'.$filename.'.webp';
    }

    /**
     * Log optimization activity
     */
    protected function logOptimization(array $data): void
    {
        if (config('image_optimization.logging.enabled', true)) {
            Log::channel(config('image_optimization.logging.channel', 'daily'))
                ->info('Image optimization completed', $data);
        }
    }
}
