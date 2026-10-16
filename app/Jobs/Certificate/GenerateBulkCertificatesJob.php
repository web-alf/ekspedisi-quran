<?php

namespace App\Jobs\Certificate;

use App\Jobs\Traits\TracksProgress;
use App\Models\WakafBatch;
use App\Services\BatchCertificateService;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class GenerateBulkCertificatesJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TracksProgress;

    public $timeout = 7200; // 2 hours for bulk processing

    public $tries = 2;

    public $backoff = [300, 900]; // 5min, 15min

    protected $wakafBatchIds;

    protected $options;

    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $wakafBatchIds, array $options = [], ?int $userId = null)
    {
        $this->wakafBatchIds = $wakafBatchIds;
        $this->options = $options;
        $this->userId = $userId ?? auth()->id();

        // Use dedicated certificate queue with connection
        $this->onConnection('redis-certificates')
            ->onQueue('certificates');
    }

    /**
     * Execute the job.
     */
    public function handle(BatchCertificateService $certificateService)
    {
        // Skip if part of a batch that was cancelled
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        try {
            $totalBatches = count($this->wakafBatchIds);

            $this->initializeProgress(
                'bulk_certificate_generation',
                "Generate sertifikat untuk {$totalBatches} batch wakaf",
                $totalBatches,
                [
                    'wakaf_batch_ids' => $this->wakafBatchIds,
                    'options' => $this->options,
                    'create_zip' => $this->options['create_zip'] ?? false,
                ]
            );

            $this->markAsProcessing();

            $startTime = microtime(true);
            $results = [
                'certificates_generated' => 0,
                'certificates_failed' => 0,
                'generated_files' => [],
                'errors' => [],
                'total_size_bytes' => 0,
            ];

            Log::info('Starting bulk certificate generation', [
                'batch_count' => $totalBatches,
                'user_id' => $this->userId,
                'create_zip' => $this->options['create_zip'] ?? false,
            ]);

            // Process batches in chunks to manage memory
            $batchChunks = array_chunk($this->wakafBatchIds, 5);

            foreach ($batchChunks as $chunkIndex => $batchChunk) {
                // Check if job should be cancelled
                if ($this->batch() && $this->batch()->cancelled()) {
                    Log::info('Bulk certificate generation cancelled');

                    return;
                }

                $chunkResult = $this->processBatchChunk($batchChunk, $certificateService);

                // Merge results
                $results['certificates_generated'] += $chunkResult['certificates_generated'];
                $results['certificates_failed'] += $chunkResult['certificates_failed'];
                $results['total_size_bytes'] += $chunkResult['total_size_bytes'];
                $results['generated_files'] = array_merge($results['generated_files'], $chunkResult['generated_files']);
                $results['errors'] = array_merge($results['errors'], $chunkResult['errors']);

                // Update progress
                $this->updateProgress(
                    $results['certificates_generated'],
                    $results['certificates_failed']
                );

                // Clear memory between chunks
                if ($chunkIndex % 2 === 0) {
                    $this->clearMemory();
                }
            }

            $processingTime = round((microtime(true) - $startTime) * 1000);

            // Create ZIP file if requested
            $zipPath = null;
            if (($this->options['create_zip'] ?? false) && count($results['generated_files']) > 0) {
                $zipPath = $this->createCertificateZip($results['generated_files']);
                if ($zipPath) {
                    $results['zip_file'] = $zipPath;
                    $results['zip_download_url'] = Storage::url($zipPath);
                }
            }

            // Final results
            $finalResults = [
                'processing_time_ms' => $processingTime,
                'processing_time_formatted' => $this->formatProcessingTime($processingTime),
                'certificates_generated' => $results['certificates_generated'],
                'certificates_failed' => $results['certificates_failed'],
                'success_rate' => $totalBatches > 0 ? round(($results['certificates_generated'] / $totalBatches) * 100, 2) : 0,
                'total_size_mb' => round($results['total_size_bytes'] / 1048576, 2),
                'zip_file' => $zipPath,
                'zip_download_url' => $zipPath ? Storage::url($zipPath) : null,
                'generated_files' => $results['generated_files'],
                'errors' => $results['errors'],
                'summary' => [
                    'total_batches' => $totalBatches,
                    'successful' => $results['certificates_generated'],
                    'failed' => $results['certificates_failed'],
                    'has_zip' => $zipPath !== null,
                ],
            ];

            $this->markAsCompleted($finalResults);

            Log::info('Bulk certificate generation completed', [
                'certificates_generated' => $results['certificates_generated'],
                'certificates_failed' => $results['certificates_failed'],
                'processing_time_ms' => $processingTime,
                'zip_created' => $zipPath !== null,
            ]);

        } catch (Exception $e) {
            Log::error('Bulk certificate generation failed', [
                'batch_ids' => $this->wakafBatchIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a chunk of wakaf batches
     */
    private function processBatchChunk(array $batchIds, BatchCertificateService $certificateService): array
    {
        $results = [
            'certificates_generated' => 0,
            'certificates_failed' => 0,
            'generated_files' => [],
            'errors' => [],
            'total_size_bytes' => 0,
        ];

        foreach ($batchIds as $batchId) {
            try {
                $wakafBatch = WakafBatch::with(['donatur', 'jenisQuran', 'sertifikat'])->find($batchId);

                if (! $wakafBatch) {
                    $results['certificates_failed']++;
                    $results['errors'][] = "WakafBatch ID {$batchId} tidak ditemukan";

                    continue;
                }

                // Check if certificate already exists and not regenerating
                if ($wakafBatch->sertifikat && ! ($this->options['regenerate'] ?? false)) {
                    $results['certificates_generated']++;

                    // Add to generated files list
                    $results['generated_files'][] = [
                        'wakaf_batch_id' => $batchId,
                        'batch_code' => $wakafBatch->batch_code,
                        'donatur_name' => $wakafBatch->donatur->nama_donatur,
                        'certificate_id' => $wakafBatch->sertifikat->id,
                        'certificate_number' => $wakafBatch->sertifikat->nomor_sertifikat,
                        'download_url' => route('admin.certificates.download', $wakafBatch->sertifikat->id),
                        'file_path' => $wakafBatch->sertifikat->file_path ?? null,
                        'status' => 'existing',
                    ];

                    continue;
                }

                // Generate new certificate
                $sertifikat = $certificateService->generateBatchCertificate($wakafBatch, $this->options);

                $results['certificates_generated']++;

                // Calculate file size if file exists
                $fileSize = 0;
                if ($sertifikat->file_path && Storage::exists($sertifikat->file_path)) {
                    $fileSize = Storage::size($sertifikat->file_path);
                    $results['total_size_bytes'] += $fileSize;
                }

                $results['generated_files'][] = [
                    'wakaf_batch_id' => $batchId,
                    'batch_code' => $wakafBatch->batch_code,
                    'donatur_name' => $wakafBatch->donatur->nama_donatur,
                    'certificate_id' => $sertifikat->id,
                    'certificate_number' => $sertifikat->nomor_sertifikat,
                    'download_url' => route('admin.certificates.download', $sertifikat->id),
                    'file_path' => $sertifikat->file_path,
                    'file_size_bytes' => $fileSize,
                    'wakif_count' => $certificateService->getWakifItemsForBatch($wakafBatch)->groupBy('wakif_name')->count(),
                    'status' => 'generated',
                ];

                Log::info('Certificate generated in bulk job', [
                    'wakaf_batch_id' => $batchId,
                    'certificate_id' => $sertifikat->id,
                    'file_size_bytes' => $fileSize,
                ]);

            } catch (Exception $e) {
                $results['certificates_failed']++;
                $errorMsg = "Batch {$batchId}: ".$e->getMessage();
                $results['errors'][] = $errorMsg;

                Log::error('Error generating certificate in bulk job', [
                    'wakaf_batch_id' => $batchId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Create ZIP file containing all generated certificates
     */
    private function createCertificateZip(array $generatedFiles): ?string
    {
        try {
            $timestamp = now()->format('Y-m-d-H-i-s');
            $zipFileName = "certificates-bulk-{$timestamp}.zip";
            $zipPath = "certificates/zip/{$zipFileName}";
            $fullZipPath = Storage::path($zipPath);

            // Ensure directory exists
            $zipDir = dirname($fullZipPath);
            if (! is_dir($zipDir)) {
                mkdir($zipDir, 0755, true);
            }

            $zip = new ZipArchive;
            if ($zip->open($fullZipPath, ZipArchive::CREATE) !== true) {
                throw new Exception('Cannot create ZIP file');
            }

            $addedFiles = 0;
            foreach ($generatedFiles as $file) {
                if (! isset($file['file_path']) || ! $file['file_path']) {
                    continue;
                }

                $fullFilePath = Storage::path($file['file_path']);
                if (file_exists($fullFilePath)) {
                    $zipEntryName = $this->sanitizeZipEntryName($file);
                    $zip->addFile($fullFilePath, $zipEntryName);
                    $addedFiles++;
                }
            }

            $zip->close();

            if ($addedFiles > 0) {
                Log::info('Created bulk certificate ZIP', [
                    'zip_path' => $zipPath,
                    'files_count' => $addedFiles,
                    'zip_size_mb' => round(filesize($fullZipPath) / 1048576, 2),
                ]);

                return $zipPath;
            } else {
                // Remove empty ZIP file
                if (file_exists($fullZipPath)) {
                    unlink($fullZipPath);
                }

                return null;
            }

        } catch (Exception $e) {
            Log::error('Failed to create certificate ZIP', [
                'error' => $e->getMessage(),
                'files_count' => count($generatedFiles),
            ]);

            return null;
        }
    }

    /**
     * Generate sanitized ZIP entry name
     */
    private function sanitizeZipEntryName(array $file): string
    {
        $donaturName = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $file['donatur_name']));
        $donaturName = substr($donaturName, 0, 30);

        return "Certificate-{$file['certificate_number']}-{$donaturName}.pdf";
    }

    /**
     * Format processing time for display
     */
    private function formatProcessingTime(int $milliseconds): string
    {
        $seconds = $milliseconds / 1000;

        if ($seconds < 60) {
            return number_format($seconds, 1).' detik';
        } elseif ($seconds < 3600) {
            return number_format($seconds / 60, 1).' menit';
        } else {
            return number_format($seconds / 3600, 1).' jam';
        }
    }

    /**
     * Clear memory to prevent buildup
     */
    private function clearMemory(): void
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Bulk certificate generation job failed permanently', [
            'batch_ids' => $this->wakafBatchIds,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Cleanup any partial files
        $this->cleanupPartialFiles();
    }

    /**
     * Clean up any partial files created during failed processing
     */
    private function cleanupPartialFiles(): void
    {
        try {
            $tempFiles = Storage::files('certificates/temp');
            $cleanedCount = 0;

            foreach ($tempFiles as $file) {
                foreach ($this->wakafBatchIds as $batchId) {
                    if (strpos($file, "batch-{$batchId}-") !== false) {
                        Storage::delete($file);
                        $cleanedCount++;
                        break;
                    }
                }
            }

            if ($cleanedCount > 0) {
                Log::info('Cleaned up partial certificate files', [
                    'files_cleaned' => $cleanedCount,
                ]);
            }
        } catch (Exception $e) {
            Log::warning('Failed to cleanup partial files', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'certificate:bulk',
            'user:'.$this->userId,
            'batches:'.count($this->wakafBatchIds),
        ];
    }
}
