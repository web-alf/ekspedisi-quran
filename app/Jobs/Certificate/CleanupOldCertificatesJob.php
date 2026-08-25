<?php

namespace App\Jobs\Certificate;

use App\Models\JobProgress;
use App\Models\Sertifikat;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupOldCertificatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour

    public $tries = 2;

    protected $daysToKeep;

    protected $cleanupType;

    protected $forceCleanup;

    /**
     * Create a new job instance.
     */
    public function __construct(int $daysToKeep = 30, string $cleanupType = 'all', bool $forceCleanup = false)
    {
        $this->daysToKeep = $daysToKeep;
        $this->cleanupType = $cleanupType; // 'certificates', 'jobs', 'temp', 'zip', 'all'
        $this->forceCleanup = $forceCleanup;

        // Use low priority queue for maintenance tasks
        $this->onConnection('redis-low')
            ->onQueue('low');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting certificate cleanup job', [
                'days_to_keep' => $this->daysToKeep,
                'cleanup_type' => $this->cleanupType,
                'force_cleanup' => $this->forceCleanup,
            ]);

            $results = [
                'certificates_cleaned' => 0,
                'certificates_size_freed_mb' => 0,
                'job_records_cleaned' => 0,
                'temp_files_cleaned' => 0,
                'temp_size_freed_mb' => 0,
                'zip_files_cleaned' => 0,
                'zip_size_freed_mb' => 0,
                'errors' => [],
            ];

            $cutoffDate = Carbon::now()->subDays($this->daysToKeep);

            // Cleanup certificate files
            if (in_array($this->cleanupType, ['certificates', 'all'])) {
                $certificateResults = $this->cleanupOldCertificateFiles($cutoffDate);
                $results = array_merge_recursive($results, $certificateResults);
            }

            // Cleanup job progress records
            if (in_array($this->cleanupType, ['jobs', 'all'])) {
                $jobResults = $this->cleanupOldJobRecords($cutoffDate);
                $results = array_merge_recursive($results, $jobResults);
            }

            // Cleanup temporary files
            if (in_array($this->cleanupType, ['temp', 'all'])) {
                $tempResults = $this->cleanupTempFiles();
                $results = array_merge_recursive($results, $tempResults);
            }

            // Cleanup old ZIP files
            if (in_array($this->cleanupType, ['zip', 'all'])) {
                $zipResults = $this->cleanupOldZipFiles($cutoffDate);
                $results = array_merge_recursive($results, $zipResults);
            }

            $totalSizeFreed = $results['certificates_size_freed_mb'] +
                            $results['temp_size_freed_mb'] +
                            $results['zip_size_freed_mb'];

            Log::info('Certificate cleanup completed', [
                'total_files_cleaned' => $results['certificates_cleaned'] + $results['temp_files_cleaned'] + $results['zip_files_cleaned'],
                'total_size_freed_mb' => round($totalSizeFreed, 2),
                'job_records_cleaned' => $results['job_records_cleaned'],
                'errors_count' => count($results['errors']),
            ]);

        } catch (\Exception $e) {
            Log::error('Certificate cleanup job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Clean up old certificate files
     */
    private function cleanupOldCertificateFiles(Carbon $cutoffDate): array
    {
        $results = [
            'certificates_cleaned' => 0,
            'certificates_size_freed_mb' => 0,
            'errors' => [],
        ];

        try {
            // Find old certificates to cleanup
            $query = Sertifikat::where('created_at', '<', $cutoffDate);

            // Don't cleanup if force cleanup is disabled and certificate is marked as important
            if (! $this->forceCleanup) {
                $query->where(function ($q) {
                    $q->where('is_sent', false)
                        ->orWhereNull('is_sent');
                });
            }

            $oldCertificates = $query->get();

            foreach ($oldCertificates as $certificate) {
                try {
                    $sizeFreed = 0;

                    // Delete physical file if exists
                    if ($certificate->file_path && Storage::exists($certificate->file_path)) {
                        $sizeFreed = Storage::size($certificate->file_path);
                        Storage::delete($certificate->file_path);
                    }

                    // Remove file_path reference but keep certificate record for audit
                    $certificate->update(['file_path' => null]);

                    $results['certificates_cleaned']++;
                    $results['certificates_size_freed_mb'] += $sizeFreed / 1048576;

                    Log::debug('Cleaned certificate file', [
                        'certificate_id' => $certificate->id,
                        'file_path' => $certificate->file_path,
                        'size_mb' => round($sizeFreed / 1048576, 2),
                    ]);

                } catch (\Exception $e) {
                    $error = "Certificate {$certificate->id}: {$e->getMessage()}";
                    $results['errors'][] = $error;
                    Log::warning('Failed to cleanup certificate', [
                        'certificate_id' => $certificate->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            $results['errors'][] = "Certificate cleanup error: {$e->getMessage()}";
            Log::error('Certificate cleanup failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Clean up old job progress records
     */
    private function cleanupOldJobRecords(Carbon $cutoffDate): array
    {
        $results = [
            'job_records_cleaned' => 0,
            'errors' => [],
        ];

        try {
            // Delete old job progress records
            $deletedCount = JobProgress::where('created_at', '<', $cutoffDate)
                ->whereIn('status', ['completed', 'failed'])
                ->delete();

            $results['job_records_cleaned'] = $deletedCount;

            Log::info('Cleaned old job progress records', [
                'records_deleted' => $deletedCount,
                'cutoff_date' => $cutoffDate->toDateString(),
            ]);

        } catch (\Exception $e) {
            $results['errors'][] = "Job records cleanup error: {$e->getMessage()}";
            Log::error('Job records cleanup failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Clean up temporary files
     */
    private function cleanupTempFiles(): array
    {
        $results = [
            'temp_files_cleaned' => 0,
            'temp_size_freed_mb' => 0,
            'errors' => [],
        ];

        try {
            $tempPaths = ['certificates/temp', 'temp'];

            foreach ($tempPaths as $tempPath) {
                if (! Storage::exists($tempPath)) {
                    continue;
                }

                $tempFiles = Storage::files($tempPath);

                foreach ($tempFiles as $file) {
                    try {
                        // Only delete files older than 1 hour
                        $fileTime = Storage::lastModified($file);
                        if ($fileTime && $fileTime < (time() - 3600)) {
                            $sizeFreed = Storage::size($file);
                            Storage::delete($file);

                            $results['temp_files_cleaned']++;
                            $results['temp_size_freed_mb'] += $sizeFreed / 1048576;
                        }
                    } catch (\Exception $e) {
                        $results['errors'][] = "Temp file {$file}: {$e->getMessage()}";
                    }
                }
            }

        } catch (\Exception $e) {
            $results['errors'][] = "Temp files cleanup error: {$e->getMessage()}";
            Log::error('Temp files cleanup failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Clean up old ZIP files
     */
    private function cleanupOldZipFiles(Carbon $cutoffDate): array
    {
        $results = [
            'zip_files_cleaned' => 0,
            'zip_size_freed_mb' => 0,
            'errors' => [],
        ];

        try {
            $zipPath = 'certificates/zip';

            if (! Storage::exists($zipPath)) {
                return $results;
            }

            $zipFiles = Storage::files($zipPath);

            foreach ($zipFiles as $file) {
                try {
                    // Check file age
                    $fileTime = Storage::lastModified($file);
                    if ($fileTime && $fileTime < $cutoffDate->timestamp) {
                        $sizeFreed = Storage::size($file);
                        Storage::delete($file);

                        $results['zip_files_cleaned']++;
                        $results['zip_size_freed_mb'] += $sizeFreed / 1048576;

                        Log::debug('Cleaned ZIP file', [
                            'file' => $file,
                            'size_mb' => round($sizeFreed / 1048576, 2),
                        ]);
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "ZIP file {$file}: {$e->getMessage()}";
                }
            }

        } catch (\Exception $e) {
            $results['errors'][] = "ZIP files cleanup error: {$e->getMessage()}";
            Log::error('ZIP files cleanup failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Exception $exception): void
    {
        Log::error('Certificate cleanup job failed permanently', [
            'days_to_keep' => $this->daysToKeep,
            'cleanup_type' => $this->cleanupType,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'cleanup:certificates',
            'maintenance',
            'cleanup_type:'.$this->cleanupType,
        ];
    }
}
