<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileStorageService
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('filesystems.storage_optimization', [
            'auto_organize' => true,
            'organize_by' => 'date', // date, type, purpose
            'cleanup_enabled' => true,
            'max_storage_size' => 5000000000, // 5GB in bytes
            'orphan_check_days' => 30,
        ]);
    }

    /**
     * Organize file upload with proper directory structure
     */
    public function organizeUpload(UploadedFile $file, string $purpose = 'general', array $options = []): array
    {
        $directory = $this->generateOrganizedPath($purpose, $options);
        $fileName = $this->generateUniqueFileName($file);
        $fullPath = $directory.'/'.$fileName;

        // Store file with organized structure
        $storedPath = $file->storeAs($directory, $fileName, 'public');

        // Track file metadata
        $this->trackFileMetadata($storedPath, $file, $purpose);

        return [
            'path' => $storedPath,
            'directory' => $directory,
            'filename' => $fileName,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'purpose' => $purpose,
            'organized' => true,
        ];
    }

    /**
     * Generate organized directory path based on configuration
     */
    protected function generateOrganizedPath(string $purpose, array $options = []): string
    {
        $basePath = $purpose;

        if ($this->config['auto_organize']) {
            switch ($this->config['organize_by']) {
                case 'date':
                    $basePath .= '/'.Carbon::now()->format('Y/m');
                    break;
                case 'type':
                    $mimeType = $options['mime_type'] ?? 'unknown';
                    $typeCategory = $this->categorizeByMimeType($mimeType);
                    $basePath .= '/'.$typeCategory;
                    break;
                case 'purpose':
                    $subPurpose = $options['sub_purpose'] ?? 'general';
                    $basePath .= '/'.$subPurpose;
                    break;
            }
        }

        return $basePath;
    }

    /**
     * Categorize files by MIME type for organization
     */
    protected function categorizeByMimeType(string $mimeType): string
    {
        $categories = [
            'images' => ['image/'],
            'documents' => ['application/pdf', 'application/msword', 'application/vnd'],
            'videos' => ['video/'],
            'audio' => ['audio/'],
            'archives' => ['application/zip', 'application/x-rar'],
        ];

        foreach ($categories as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_starts_with($mimeType, $pattern)) {
                    return $category;
                }
            }
        }

        return 'misc';
    }

    /**
     * Generate unique filename to prevent conflicts
     */
    protected function generateUniqueFileName(UploadedFile $file): string
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();

        // Sanitize filename
        $sanitizedName = preg_replace('/[^a-zA-Z0-9\-_]/', '', $originalName);
        $sanitizedName = substr($sanitizedName, 0, 50); // Limit length

        // Add timestamp and random string for uniqueness
        $uniqueId = time().'_'.substr(md5(uniqid()), 0, 8);

        return $sanitizedName.'_'.$uniqueId.'.'.$extension;
    }

    /**
     * Track file metadata for management
     */
    protected function trackFileMetadata(string $path, UploadedFile $file, string $purpose): void
    {
        try {
            DB::table('file_metadata')->insert([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'purpose' => $purpose,
                'created_at' => now(),
                'updated_at' => now(),
                'last_accessed' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to track file metadata', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get storage usage statistics
     */
    public function getStorageStats(): array
    {
        $totalSize = 0;
        $fileCount = 0;
        $directoryStats = [];

        $disk = Storage::disk('public');
        $allFiles = $this->getAllFiles($disk);

        foreach ($allFiles as $file) {
            $size = $disk->size($file);
            $totalSize += $size;
            $fileCount++;

            // Categorize by directory
            $directory = dirname($file);
            if (! isset($directoryStats[$directory])) {
                $directoryStats[$directory] = ['size' => 0, 'count' => 0];
            }
            $directoryStats[$directory]['size'] += $size;
            $directoryStats[$directory]['count']++;
        }

        // Sort directories by size
        uasort($directoryStats, function ($a, $b) {
            return $b['size'] - $a['size'];
        });

        return [
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatFileSize($totalSize),
            'file_count' => $fileCount,
            'directory_stats' => $directoryStats,
            'max_storage_size' => $this->config['max_storage_size'],
            'usage_percentage' => round(($totalSize / $this->config['max_storage_size']) * 100, 2),
            'available_space' => $this->config['max_storage_size'] - $totalSize,
            'available_space_formatted' => $this->formatFileSize($this->config['max_storage_size'] - $totalSize),
        ];
    }

    /**
     * Find orphaned files that are not referenced in database
     */
    public function findOrphanedFiles(): array
    {
        $disk = Storage::disk('public');
        $allFiles = $this->getAllFiles($disk);
        $orphanedFiles = [];

        foreach ($allFiles as $file) {
            if ($this->isFileOrphaned($file)) {
                $orphanedFiles[] = [
                    'path' => $file,
                    'size' => $disk->size($file),
                    'last_modified' => Carbon::createFromTimestamp($disk->lastModified($file)),
                    'age_days' => Carbon::createFromTimestamp($disk->lastModified($file))->diffInDays(now()),
                ];
            }
        }

        return $orphanedFiles;
    }

    /**
     * Check if file is orphaned (not referenced in database)
     */
    protected function isFileOrphaned(string $filePath): bool
    {
        // Skip system files and thumbnails directory
        if (str_contains($filePath, 'thumbnails/') ||
            str_contains($filePath, '.gitkeep') ||
            str_contains($filePath, '.DS_Store')) {
            return false;
        }

        // Check common database columns that might reference files
        $tables = [
            'users' => ['avatar', 'profile_picture'],
            'galleries' => ['image_path', 'thumbnail_path'],
            'testimonials' => ['image_path'],
            'settings' => ['logo', 'favicon', 'hero_image'],
            'certificate_templates' => ['template_file_path'],
            'mushaf_requests' => ['foto_santri', 'foto_lembaga', 'file_nama_santri'],
        ];

        foreach ($tables as $table => $columns) {
            foreach ($columns as $column) {
                try {
                    $exists = DB::table($table)
                        ->where($column, 'LIKE', '%'.$filePath.'%')
                        ->exists();

                    if ($exists) {
                        return false; // File is referenced
                    }
                } catch (\Exception $e) {
                    // Table or column might not exist, continue checking
                    continue;
                }
            }
        }

        // Check file_metadata table if it exists
        try {
            $exists = DB::table('file_metadata')
                ->where('path', $filePath)
                ->exists();

            if ($exists) {
                return false;
            }
        } catch (\Exception $e) {
            // Table might not exist
        }

        return true; // File appears to be orphaned
    }

    /**
     * Clean up old and orphaned files
     */
    public function cleanupFiles(array $options = []): array
    {
        $defaultOptions = [
            'remove_orphaned' => true,
            'remove_old_thumbnails' => true,
            'remove_temp_files' => true,
            'orphan_age_days' => 30,
            'dry_run' => false,
        ];

        $options = array_merge($defaultOptions, $options);
        $cleanupResults = [
            'orphaned_files' => [],
            'old_thumbnails' => [],
            'temp_files' => [],
            'total_size_freed' => 0,
            'total_files_removed' => 0,
        ];

        if ($options['remove_orphaned']) {
            $orphanedFiles = $this->findOrphanedFiles();
            foreach ($orphanedFiles as $file) {
                if ($file['age_days'] >= $options['orphan_age_days']) {
                    if (! $options['dry_run']) {
                        Storage::disk('public')->delete($file['path']);
                    }
                    $cleanupResults['orphaned_files'][] = $file;
                    $cleanupResults['total_size_freed'] += $file['size'];
                    $cleanupResults['total_files_removed']++;
                }
            }
        }

        if ($options['remove_old_thumbnails']) {
            $oldThumbnails = $this->findOldThumbnails();
            foreach ($oldThumbnails as $thumbnail) {
                if (! $options['dry_run']) {
                    Storage::disk('public')->delete($thumbnail['path']);
                }
                $cleanupResults['old_thumbnails'][] = $thumbnail;
                $cleanupResults['total_size_freed'] += $thumbnail['size'];
                $cleanupResults['total_files_removed']++;
            }
        }

        if ($options['remove_temp_files']) {
            $tempFiles = $this->findTempFiles();
            foreach ($tempFiles as $tempFile) {
                if (! $options['dry_run']) {
                    Storage::disk('public')->delete($tempFile['path']);
                }
                $cleanupResults['temp_files'][] = $tempFile;
                $cleanupResults['total_size_freed'] += $tempFile['size'];
                $cleanupResults['total_files_removed']++;
            }
        }

        $cleanupResults['total_size_freed_formatted'] = $this->formatFileSize($cleanupResults['total_size_freed']);

        Log::info('File cleanup completed', [
            'dry_run' => $options['dry_run'],
            'files_removed' => $cleanupResults['total_files_removed'],
            'size_freed' => $cleanupResults['total_size_freed_formatted'],
        ]);

        return $cleanupResults;
    }

    /**
     * Find old thumbnail files that don't have corresponding main images
     */
    protected function findOldThumbnails(): array
    {
        $disk = Storage::disk('public');
        $thumbnails = [];

        $allFiles = $this->getAllFiles($disk);

        foreach ($allFiles as $file) {
            if (str_contains($file, '/thumbnails/')) {
                $mainImagePath = $this->getMainImagePath($file);
                if (! $disk->exists($mainImagePath)) {
                    $thumbnails[] = [
                        'path' => $file,
                        'size' => $disk->size($file),
                        'main_image_path' => $mainImagePath,
                    ];
                }
            }
        }

        return $thumbnails;
    }

    /**
     * Get main image path from thumbnail path
     */
    protected function getMainImagePath(string $thumbnailPath): string
    {
        // Remove /thumbnails/ from path and thumb_ prefix
        $mainPath = str_replace('/thumbnails/', '/', $thumbnailPath);
        $fileName = basename($mainPath);
        $directory = dirname($mainPath);

        if (str_starts_with($fileName, 'thumb_')) {
            $fileName = substr($fileName, 6); // Remove 'thumb_' prefix
        }

        return $directory.'/'.$fileName;
    }

    /**
     * Find temporary files that can be cleaned up
     */
    protected function findTempFiles(): array
    {
        $disk = Storage::disk('public');
        $tempFiles = [];
        $tempPatterns = [
            '/tmp/',
            '/temp/',
            '/cache/',
            '.tmp',
            '.temp',
        ];

        $allFiles = $this->getAllFiles($disk);

        foreach ($allFiles as $file) {
            foreach ($tempPatterns as $pattern) {
                if (str_contains($file, $pattern)) {
                    $tempFiles[] = [
                        'path' => $file,
                        'size' => $disk->size($file),
                        'last_modified' => Carbon::createFromTimestamp($disk->lastModified($file)),
                    ];
                    break;
                }
            }
        }

        return $tempFiles;
    }

    /**
     * Get all files recursively from storage disk
     */
    protected function getAllFiles($disk): array
    {
        $allFiles = [];
        $directories = [''];

        while (! empty($directories)) {
            $currentDir = array_shift($directories);

            try {
                $files = $disk->files($currentDir);
                $allFiles = array_merge($allFiles, $files);

                $subdirectories = $disk->directories($currentDir);
                $directories = array_merge($directories, $subdirectories);
            } catch (\Exception $e) {
                Log::warning('Error reading directory', [
                    'directory' => $currentDir,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $allFiles;
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
     * Move file to organized structure
     */
    public function reorganizeFile(string $currentPath, string $purpose = 'general'): array
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($currentPath)) {
            throw new \InvalidArgumentException("File not found: {$currentPath}");
        }

        $fileInfo = pathinfo($currentPath);
        $newDirectory = $this->generateOrganizedPath($purpose);
        $newFileName = $this->generateUniqueFileName(
            new class($fileInfo['basename'])
            {
                public function __construct(private string $name) {}

                public function getClientOriginalName()
                {
                    return $this->name;
                }

                public function getClientOriginalExtension()
                {
                    return pathinfo($this->name, PATHINFO_EXTENSION);
                }
            }
        );

        $newPath = $newDirectory.'/'.$newFileName;

        // Create directory if it doesn't exist
        $disk->makeDirectory($newDirectory);

        // Move file
        $disk->move($currentPath, $newPath);

        return [
            'old_path' => $currentPath,
            'new_path' => $newPath,
            'reorganized' => true,
        ];
    }

    /**
     * Update file access time for usage tracking
     */
    public function recordFileAccess(string $filePath): void
    {
        try {
            DB::table('file_metadata')
                ->where('path', $filePath)
                ->update(['last_accessed' => now()]);
        } catch (\Exception $e) {
            // Metadata tracking is optional
        }
    }

    /**
     * Get file usage statistics
     */
    public function getFileUsageStats(int $days = 30): array
    {
        try {
            $stats = DB::table('file_metadata')
                ->select(
                    DB::raw('COUNT(*) as total_files'),
                    DB::raw('SUM(size) as total_size'),
                    DB::raw('AVG(size) as avg_size'),
                    'purpose',
                    DB::raw('COUNT(CASE WHEN last_accessed >= ? THEN 1 END) as accessed_recently')
                )
                ->where('created_at', '>=', now()->subDays($days))
                ->groupBy('purpose')
                ->setBindings([now()->subDays($days)])
                ->get();

            return $stats->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}
