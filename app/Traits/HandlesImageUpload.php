<?php

namespace App\Traits;

use App\Services\ImageOptimizationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HandlesImageUpload
{
    /**
     * Upload dan optimasi gambar
     * 
     * @param UploadedFile $file
     * @param string $directory
     * @param array $options
     * @return string|null Path gambar yang disimpan
     */
    protected function uploadOptimizedImage(UploadedFile $file, string $directory = 'images', array $options = []): ?string
    {
        $imageService = app(ImageOptimizationService::class);
        
        try {
            $result = $imageService->optimizeAndStore($file, $directory, $options);
            return $result['path'];
        } catch (\Exception $e) {
            // Log error
            \Log::error('Image upload failed: ' . $e->getMessage());
            
            // Fallback ke upload biasa jika optimization gagal
            return $this->fallbackUpload($file, $directory);
        }
    }

    /**
     * Upload multiple gambar dengan optimasi
     */
    protected function uploadMultipleOptimizedImages(array $files, string $directory = 'images', array $options = []): array
    {
        $imageService = app(ImageOptimizationService::class);
        $paths = [];
        
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                try {
                    $result = $imageService->optimizeAndStore($file, $directory, $options);
                    $paths[] = $result['path'];
                } catch (\Exception $e) {
                    \Log::error('Image upload failed: ' . $e->getMessage());
                    
                    // Fallback untuk file individual
                    $fallbackPath = $this->fallbackUpload($file, $directory);
                    if ($fallbackPath) {
                        $paths[] = $fallbackPath;
                    }
                }
            }
        }
        
        return $paths;
    }

    /**
     * Upload dengan replacement (hapus file lama)
     */
    protected function replaceOptimizedImage(UploadedFile $file, ?string $oldPath, string $directory = 'images', array $options = []): ?string
    {
        // Upload gambar baru
        $newPath = $this->uploadOptimizedImage($file, $directory, $options);
        
        // Hapus file lama jika upload berhasil
        if ($newPath && $oldPath) {
            $this->deleteImageWithThumbnail($oldPath);
        }
        
        return $newPath;
    }

    /**
     * Hapus gambar beserta thumbnail
     */
    protected function deleteImageWithThumbnail(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        $deleted = false;
        
        // Hapus file utama
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            $deleted = true;
        }
        
        // Hapus thumbnail jika ada
        $thumbnailPath = $this->getThumbnailPath($path);
        if (Storage::disk('public')->exists($thumbnailPath)) {
            Storage::disk('public')->delete($thumbnailPath);
        }
        
        return $deleted;
    }

    /**
     * Get path thumbnail dari path utama
     */
    protected function getThumbnailPath(string $path): string
    {
        $pathInfo = pathinfo($path);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['basename'];
        
        return $directory . '/thumbnails/thumb_' . $filename;
    }

    /**
     * Fallback upload tanpa optimasi (untuk backward compatibility)
     */
    protected function fallbackUpload(UploadedFile $file, string $directory): ?string
    {
        try {
            return $file->store($directory, 'public');
        } catch (\Exception $e) {
            \Log::error('Fallback upload failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validasi gambar dengan custom rules
     */
    protected function validateImageUpload(array $rules = []): array
    {
        $defaultRules = [
            'mimes:jpeg,png,jpg,gif,webp',
            'max:10240', // 10MB
        ];
        
        return array_merge($defaultRules, $rules);
    }

    /**
     * Get URL gambar dengan fallback
     */
    protected function getImageUrl(?string $path, bool $thumbnail = false): ?string
    {
        if (!$path) {
            return null;
        }
        
        $imagePath = $thumbnail ? $this->getThumbnailPath($path) : $path;
        
        // Cek apakah file ada
        if (Storage::disk('public')->exists($imagePath)) {
            return Storage::disk('public')->url($imagePath);
        }
        
        // Fallback ke gambar utama jika thumbnail tidak ada
        if ($thumbnail && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }
        
        return null;
    }

    /**
     * Get informasi gambar
     */
    protected function getImageInfo(?string $path): ?array
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }
        
        $fullPath = Storage::disk('public')->path($path);
        $imageInfo = getimagesize($fullPath);
        
        if (!$imageInfo) {
            return null;
        }
        
        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'thumbnail_url' => $this->getImageUrl($path, true),
            'size' => Storage::disk('public')->size($path),
            'size_formatted' => $this->formatFileSize(Storage::disk('public')->size($path)),
            'dimensions' => [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1]
            ],
            'mime_type' => $imageInfo['mime'] ?? null,
        ];
    }

    /**
     * Format ukuran file ke format human readable
     */
    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Buat konfigurasi optimasi berdasarkan tipe konten
     */
    protected function getOptimizationConfig(string $type = 'default'): array
    {
        $configs = [
            'default' => [
                'webp_quality' => 85,
                'max_width' => 1920,
                'max_height' => 1080,
                'create_thumbnail' => true,
            ],
            'gallery' => [
                'webp_quality' => 90,
                'max_width' => 1920,
                'max_height' => 1080,
                'thumbnail_width' => 400,
                'thumbnail_height' => 300,
                'create_thumbnail' => true,
            ],
            'testimonial' => [
                'webp_quality' => 85,
                'max_width' => 800,
                'max_height' => 600,
                'thumbnail_width' => 150,
                'thumbnail_height' => 150,
                'create_thumbnail' => true,
            ],
            'avatar' => [
                'webp_quality' => 85,
                'max_width' => 400,
                'max_height' => 400,
                'thumbnail_width' => 100,
                'thumbnail_height' => 100,
                'create_thumbnail' => true,
            ],
            'logo' => [
                'webp_quality' => 95,
                'max_width' => 800,
                'max_height' => 400,
                'create_thumbnail' => false,
            ],
        ];
        
        return $configs[$type] ?? $configs['default'];
    }
}