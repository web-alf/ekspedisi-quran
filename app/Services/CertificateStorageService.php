<?php

namespace App\Services;

use App\Models\Sertifikat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CertificateStorageService
{
    const STORAGE_DISK = 'local';

    const CERTIFICATE_PATH = 'certificates';

    const TEMP_PATH = 'certificates/temp';

    const ZIP_PATH = 'certificates/zip';

    const BACKUP_PATH = 'certificates/backup';

    /**
     * Get storage statistics
     */
    public function getStorageStats(): array
    {
        try {
            $stats = [
                'certificates' => $this->getDirectoryStats(self::CERTIFICATE_PATH),
                'temp' => $this->getDirectoryStats(self::TEMP_PATH),
                'zip' => $this->getDirectoryStats(self::ZIP_PATH),
                'total_size_mb' => 0,
                'total_files' => 0,
                'oldest_file' => null,
                'newest_file' => null,
            ];

            // Calculate totals
            foreach (['certificates', 'temp', 'zip'] as $type) {
                $stats['total_size_mb'] += $stats[$type]['size_mb'];
                $stats['total_files'] += $stats[$type]['file_count'];
            }

            // Find oldest and newest files
            $allFiles = $this->getAllCertificateFiles();
            if (! empty($allFiles)) {
                $oldestTime = min(array_column($allFiles, 'modified'));
                $newestTime = max(array_column($allFiles, 'modified'));

                $stats['oldest_file'] = Carbon::createFromTimestamp($oldestTime);
                $stats['newest_file'] = Carbon::createFromTimestamp($newestTime);
            }

            return $stats;

        } catch (\Exception $e) {
            Log::error('Failed to get storage stats', ['error' => $e->getMessage()]);

            return [
                'error' => $e->getMessage(),
                'certificates' => ['size_mb' => 0, 'file_count' => 0],
                'temp' => ['size_mb' => 0, 'file_count' => 0],
                'zip' => ['size_mb' => 0, 'file_count' => 0],
                'total_size_mb' => 0,
                'total_files' => 0,
            ];
        }
    }

    /**
     * Get directory statistics
     */
    private function getDirectoryStats(string $path): array
    {
        try {
            if (! Storage::disk(self::STORAGE_DISK)->exists($path)) {
                return ['size_mb' => 0, 'file_count' => 0, 'subdirectories' => []];
            }

            $files = Storage::disk(self::STORAGE_DISK)->allFiles($path);
            $totalSize = 0;
            $fileCount = count($files);

            foreach ($files as $file) {
                $totalSize += Storage::disk(self::STORAGE_DISK)->size($file);
            }

            // Get subdirectory info for certificates
            $subdirectories = [];
            if ($path === self::CERTIFICATE_PATH) {
                $subdirectories = $this->getCertificateSubdirectoryStats();
            }

            return [
                'size_mb' => round($totalSize / 1048576, 2),
                'file_count' => $fileCount,
                'subdirectories' => $subdirectories,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to get stats for {$path}", ['error' => $e->getMessage()]);

            return ['size_mb' => 0, 'file_count' => 0, 'subdirectories' => []];
        }
    }

    /**
     * Get subdirectory statistics for certificates
     */
    private function getCertificateSubdirectoryStats(): array
    {
        $subdirs = [];

        try {
            $directories = Storage::disk(self::STORAGE_DISK)->directories(self::CERTIFICATE_PATH);

            foreach ($directories as $dir) {
                if (basename($dir) === 'temp' || basename($dir) === 'zip') {
                    continue;
                }

                $files = Storage::disk(self::STORAGE_DISK)->allFiles($dir);
                $totalSize = 0;

                foreach ($files as $file) {
                    $totalSize += Storage::disk(self::STORAGE_DISK)->size($file);
                }

                $subdirs[basename($dir)] = [
                    'size_mb' => round($totalSize / 1048576, 2),
                    'file_count' => count($files),
                ];
            }

        } catch (\Exception $e) {
            Log::error('Failed to get certificate subdirectory stats', ['error' => $e->getMessage()]);
        }

        return $subdirs;
    }

    /**
     * Get all certificate files with metadata
     */
    private function getAllCertificateFiles(): array
    {
        $files = [];

        try {
            $paths = [self::CERTIFICATE_PATH, self::TEMP_PATH, self::ZIP_PATH];

            foreach ($paths as $path) {
                if (! Storage::disk(self::STORAGE_DISK)->exists($path)) {
                    continue;
                }

                $pathFiles = Storage::disk(self::STORAGE_DISK)->allFiles($path);

                foreach ($pathFiles as $file) {
                    $files[] = [
                        'path' => $file,
                        'size' => Storage::disk(self::STORAGE_DISK)->size($file),
                        'modified' => Storage::disk(self::STORAGE_DISK)->lastModified($file),
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to get all certificate files', ['error' => $e->getMessage()]);
        }

        return $files;
    }

    /**
     * Clean up orphaned certificate files
     */
    public function cleanupOrphanedFiles(): array
    {
        $results = [
            'orphaned_files_found' => 0,
            'orphaned_files_cleaned' => 0,
            'size_freed_mb' => 0,
            'errors' => [],
        ];

        try {
            // Get all certificate files
            $certificateFiles = Storage::disk(self::STORAGE_DISK)->allFiles(self::CERTIFICATE_PATH);

            // Exclude temp and zip directories
            $certificateFiles = array_filter($certificateFiles, function ($file) {
                return ! str_contains($file, '/temp/') && ! str_contains($file, '/zip/');
            });

            // Get all file paths referenced in database
            $referencedPaths = Sertifikat::whereNotNull('file_path')
                ->pluck('file_path')
                ->toArray();

            foreach ($certificateFiles as $file) {
                // Check if file is referenced in database
                if (! in_array($file, $referencedPaths)) {
                    $results['orphaned_files_found']++;

                    try {
                        $size = Storage::disk(self::STORAGE_DISK)->size($file);
                        Storage::disk(self::STORAGE_DISK)->delete($file);

                        $results['orphaned_files_cleaned']++;
                        $results['size_freed_mb'] += $size / 1048576;

                        Log::info('Cleaned orphaned certificate file', [
                            'file' => $file,
                            'size_mb' => round($size / 1048576, 2),
                        ]);

                    } catch (\Exception $e) {
                        $results['errors'][] = "Failed to delete {$file}: {$e->getMessage()}";
                    }
                }
            }

        } catch (\Exception $e) {
            $results['errors'][] = "Orphaned files cleanup error: {$e->getMessage()}";
            Log::error('Orphaned files cleanup failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Validate certificate file integrity
     */
    public function validateCertificateFiles(): array
    {
        $results = [
            'total_certificates' => 0,
            'valid_files' => 0,
            'missing_files' => 0,
            'corrupted_files' => 0,
            'invalid_references' => 0,
            'issues' => [],
        ];

        try {
            $certificates = Sertifikat::whereNotNull('file_path')->get();
            $results['total_certificates'] = $certificates->count();

            foreach ($certificates as $certificate) {
                try {
                    if (! $certificate->file_path) {
                        continue;
                    }

                    // Check if file exists
                    if (! Storage::disk(self::STORAGE_DISK)->exists($certificate->file_path)) {
                        $results['missing_files']++;
                        $results['issues'][] = [
                            'type' => 'missing_file',
                            'certificate_id' => $certificate->id,
                            'file_path' => $certificate->file_path,
                            'message' => 'File referenced in database does not exist',
                        ];

                        continue;
                    }

                    // Check file size (PDF files should be at least 1KB)
                    $fileSize = Storage::disk(self::STORAGE_DISK)->size($certificate->file_path);
                    if ($fileSize < 1024) {
                        $results['corrupted_files']++;
                        $results['issues'][] = [
                            'type' => 'corrupted_file',
                            'certificate_id' => $certificate->id,
                            'file_path' => $certificate->file_path,
                            'file_size' => $fileSize,
                            'message' => 'File is too small to be a valid PDF',
                        ];

                        continue;
                    }

                    // Check file header (PDF files should start with %PDF)
                    $fileContent = Storage::disk(self::STORAGE_DISK)->get($certificate->file_path);
                    if (! str_starts_with($fileContent, '%PDF')) {
                        $results['corrupted_files']++;
                        $results['issues'][] = [
                            'type' => 'invalid_format',
                            'certificate_id' => $certificate->id,
                            'file_path' => $certificate->file_path,
                            'message' => 'File does not appear to be a valid PDF',
                        ];

                        continue;
                    }

                    $results['valid_files']++;

                } catch (\Exception $e) {
                    $results['invalid_references']++;
                    $results['issues'][] = [
                        'type' => 'validation_error',
                        'certificate_id' => $certificate->id,
                        'file_path' => $certificate->file_path ?? 'null',
                        'message' => $e->getMessage(),
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Certificate file validation failed', ['error' => $e->getMessage()]);
            $results['issues'][] = [
                'type' => 'system_error',
                'message' => $e->getMessage(),
            ];
        }

        return $results;
    }

    /**
     * Create backup of important certificates
     */
    public function createBackup(array $certificateIds = []): array
    {
        $results = [
            'certificates_backed_up' => 0,
            'backup_size_mb' => 0,
            'backup_path' => null,
            'errors' => [],
        ];

        try {
            // Create backup directory if it doesn't exist
            if (! Storage::disk(self::STORAGE_DISK)->exists(self::BACKUP_PATH)) {
                Storage::disk(self::STORAGE_DISK)->makeDirectory(self::BACKUP_PATH);
            }

            $backupDir = self::BACKUP_PATH.'/'.now()->format('Y-m-d-H-i-s');
            Storage::disk(self::STORAGE_DISK)->makeDirectory($backupDir);

            $query = Sertifikat::whereNotNull('file_path');

            if (! empty($certificateIds)) {
                $query->whereIn('id', $certificateIds);
            } else {
                // Backup only important certificates (sent or recent)
                $query->where(function ($q) {
                    $q->where('is_sent', true)
                        ->orWhere('created_at', '>=', now()->subDays(7));
                });
            }

            $certificates = $query->get();

            foreach ($certificates as $certificate) {
                try {
                    if (! Storage::disk(self::STORAGE_DISK)->exists($certificate->file_path)) {
                        $results['errors'][] = "Certificate {$certificate->id}: File not found";

                        continue;
                    }

                    $backupFileName = "cert-{$certificate->id}-{$certificate->nomor_sertifikat}.pdf";
                    $backupPath = $backupDir.'/'.$backupFileName;

                    Storage::disk(self::STORAGE_DISK)->copy($certificate->file_path, $backupPath);

                    $fileSize = Storage::disk(self::STORAGE_DISK)->size($backupPath);
                    $results['certificates_backed_up']++;
                    $results['backup_size_mb'] += $fileSize / 1048576;

                } catch (\Exception $e) {
                    $results['errors'][] = "Certificate {$certificate->id}: {$e->getMessage()}";
                }
            }

            $results['backup_path'] = $backupDir;
            $results['backup_size_mb'] = round($results['backup_size_mb'], 2);

            Log::info('Certificate backup completed', [
                'certificates_backed_up' => $results['certificates_backed_up'],
                'backup_size_mb' => $results['backup_size_mb'],
                'backup_path' => $backupDir,
            ]);

        } catch (\Exception $e) {
            $results['errors'][] = "Backup error: {$e->getMessage()}";
            Log::error('Certificate backup failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Get storage recommendations
     */
    public function getStorageRecommendations(): array
    {
        $stats = $this->getStorageStats();
        $recommendations = [];

        // Check total storage usage
        if ($stats['total_size_mb'] > 1000) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Storage tinggi',
                'message' => "Total penyimpanan sertifikat: {$stats['total_size_mb']} MB. Pertimbangkan untuk melakukan cleanup file lama.",
                'action' => 'cleanup_old_files',
            ];
        }

        // Check temporary files
        if ($stats['temp']['file_count'] > 10) {
            $recommendations[] = [
                'type' => 'info',
                'title' => 'File temporary',
                'message' => "Terdapat {$stats['temp']['file_count']} file temporary. Jalankan cleanup untuk membersihkan file yang tidak diperlukan.",
                'action' => 'cleanup_temp_files',
            ];
        }

        // Check ZIP files
        if ($stats['zip']['size_mb'] > 100) {
            $recommendations[] = [
                'type' => 'info',
                'title' => 'File ZIP',
                'message' => "File ZIP menggunakan {$stats['zip']['size_mb']} MB storage. Hapus file ZIP lama yang sudah tidak diperlukan.",
                'action' => 'cleanup_zip_files',
            ];
        }

        // Check file age
        if (isset($stats['oldest_file']) && $stats['oldest_file']->diffInDays(now()) > 90) {
            $recommendations[] = [
                'type' => 'suggestion',
                'title' => 'File lama',
                'message' => "File tertua berusia {$stats['oldest_file']->diffInDays(now())} hari. Backup dan arsipkan file lama untuk menghemat storage.",
                'action' => 'archive_old_files',
            ];
        }

        return $recommendations;
    }
}
