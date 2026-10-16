<?php

namespace App\Jobs\Warehouse;

use App\Jobs\Traits\TracksProgress;
use App\Models\Donatur;
use App\Services\ConsolidatedCertificateService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CertificateGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TracksProgress;

    public $timeout = 3600; // 1 hour

    public $tries = 3;

    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    protected $donaturIds;

    protected $certificateType;

    protected $parameters;

    protected $userId;

    public function __construct(
        array $donaturIds,
        string $certificateType = 'consolidated',
        array $parameters = [],
        ?int $userId = null
    ) {
        $this->donaturIds = $donaturIds;
        $this->certificateType = $certificateType;
        $this->parameters = $parameters;
        $this->userId = $userId ?? auth()->id();

        // Use dedicated certificate queue with connection
        $this->onConnection('redis-certificates')
            ->onQueue('certificates');
    }

    public function handle(ConsolidatedCertificateService $certificateService)
    {
        try {
            $this->initializeProgress(
                'consolidated_certificate_generation',
                "Generate sertifikat {$this->certificateType} untuk ".count($this->donaturIds).' donatur',
                count($this->donaturIds),
                [
                    'certificate_type' => $this->certificateType,
                    'parameters' => $this->parameters,
                ]
            );

            $this->markAsProcessing();

            $startTime = microtime(true);
            $results = [
                'certificates_generated' => 0,
                'certificates_failed' => 0,
                'total_size_bytes' => 0,
                'generated_files' => [],
                'errors' => [],
            ];

            Log::info('Starting consolidated certificate generation', [
                'donatur_count' => count($this->donaturIds),
                'certificate_type' => $this->certificateType,
                'user_id' => $this->userId,
            ]);

            // Process donatur in chunks to manage memory
            $donaturChunks = array_chunk($this->donaturIds, 5); // Reduced chunk size for better memory management

            foreach ($donaturChunks as $chunkIndex => $donaturChunk) {
                try {
                    $chunkResult = $this->processDonatursChunk($donaturChunk, $certificateService);

                    $results['certificates_generated'] += $chunkResult['certificates_generated'];
                    $results['certificates_failed'] += $chunkResult['certificates_failed'];
                    $results['total_size_bytes'] += $chunkResult['total_size_bytes'];
                    $results['generated_files'] = array_merge($results['generated_files'], $chunkResult['generated_files']);
                    $results['errors'] = array_merge($results['errors'], $chunkResult['errors']);

                    $this->updateProgress(
                        $results['certificates_generated'],
                        $results['certificates_failed']
                    );

                } catch (Exception $e) {
                    Log::error('Error processing certificate chunk: '.$e->getMessage(), [
                        'chunk_index' => $chunkIndex,
                        'donatur_ids' => $donaturChunk,
                    ]);
                    $results['errors'][] = 'Chunk '.($chunkIndex + 1).': '.$e->getMessage();
                    $results['certificates_failed'] += count($donaturChunk);
                }

                // Clear memory between chunks
                if ($chunkIndex % 2 === 0) {
                    $this->clearMemory();
                }
            }

            $processingTime = round((microtime(true) - $startTime) * 1000);

            // Generate combined ZIP if requested
            $zipPath = null;
            if (($this->parameters['create_zip'] ?? false) && count($results['generated_files']) > 0) {
                $zipPath = $this->createCombinedZip($results['generated_files']);
            }

            $finalResults = [
                'processing_time_ms' => $processingTime,
                'processing_time_formatted' => $this->formatProcessingTime($processingTime),
                'certificate_type' => $this->certificateType,
                'certificates_generated' => $results['certificates_generated'],
                'certificates_failed' => $results['certificates_failed'],
                'total_size_mb' => round($results['total_size_bytes'] / 1048576, 2),
                'zip_file' => $zipPath,
                'zip_download_url' => $zipPath ? Storage::url($zipPath) : null,
                'success_rate' => count($this->donaturIds) > 0 ?
                    round(($results['certificates_generated'] / count($this->donaturIds)) * 100, 2) : 0,
                'errors' => $results['errors'],
                'generated_files' => $results['generated_files'],
            ];

            $this->markAsCompleted($finalResults);

            Log::info('Consolidated certificate generation completed', [
                'certificates_generated' => $results['certificates_generated'],
                'certificates_failed' => $results['certificates_failed'],
                'processing_time_ms' => $processingTime,
            ]);

        } catch (Exception $e) {
            Log::error('Certificate generation job failed: '.$e->getMessage(), [
                'donatur_ids' => $this->donaturIds,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    protected function processDonatursChunk(array $donaturIds, ConsolidatedCertificateService $certificateService)
    {
        $results = [
            'certificates_generated' => 0,
            'certificates_failed' => 0,
            'total_size_bytes' => 0,
            'generated_files' => [],
            'errors' => [],
        ];

        foreach ($donaturIds as $donaturId) {
            try {
                $donatur = Donatur::with(['pengiriman.jenisQuran'])->find($donaturId);

                if (! $donatur) {
                    $results['errors'][] = "Donatur ID {$donaturId} tidak ditemukan";
                    $results['certificates_failed']++;

                    continue;
                }

                // Generate consolidated certificate
                $sertifikat = $certificateService->createConsolidatedCertificate($donatur, $this->parameters);

                if ($sertifikat) {
                    $results['certificates_generated']++;

                    // Calculate file size if stored
                    $fileSize = 0;
                    if ($sertifikat->file_path && Storage::exists($sertifikat->file_path)) {
                        $fileSize = Storage::size($sertifikat->file_path);
                        $results['total_size_bytes'] += $fileSize;
                    }

                    $results['generated_files'][] = [
                        'donatur_id' => $donaturId,
                        'donatur_name' => $donatur->nama_donatur,
                        'certificate_id' => $sertifikat->id,
                        'certificate_number' => $sertifikat->nomor_sertifikat,
                        'file_path' => $sertifikat->file_path,
                        'file_size_bytes' => $fileSize,
                        'download_url' => route('admin.certificates.download', $sertifikat->id),
                        'preview_url' => route('admin.certificates.preview', $sertifikat->id),
                    ];
                } else {
                    $results['certificates_failed']++;
                    $results['errors'][] = "Failed to generate consolidated certificate for donatur: {$donatur->nama_donatur}";
                }

            } catch (Exception $e) {
                $results['certificates_failed']++;
                $results['errors'][] = "Error generating certificate for donatur {$donaturId}: ".$e->getMessage();
                Log::error('Consolidated certificate generation error', [
                    'donatur_id' => $donaturId,
                    'certificate_type' => $this->certificateType,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Format processing time for display
     */
    protected function formatProcessingTime(int $milliseconds): string
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

    protected function createCombinedZip(array $generatedFiles): ?string
    {
        try {
            $zipFilename = "certificates-{$this->certificateType}-".now()->format('Y-m-d-H-i-s').'.zip';
            $zipPath = "certificates/zip/{$zipFilename}";

            $zip = new \ZipArchive;
            $fullZipPath = Storage::path($zipPath);

            // Ensure directory exists
            $zipDir = dirname($fullZipPath);
            if (! is_dir($zipDir)) {
                mkdir($zipDir, 0755, true);
            }

            if ($zip->open($fullZipPath, \ZipArchive::CREATE) === true) {
                $addedFiles = 0;
                foreach ($generatedFiles as $file) {
                    if (! isset($file['file_path']) || ! $file['file_path']) {
                        continue;
                    }

                    $fullFilePath = Storage::path($file['file_path']);
                    if (file_exists($fullFilePath)) {
                        $zipEntryName = $this->generateZipEntryName($file);
                        $zip->addFile($fullFilePath, $zipEntryName);
                        $addedFiles++;
                    }
                }
                $zip->close();

                if ($addedFiles > 0) {
                    Log::info('Created consolidated certificate ZIP', [
                        'zip_path' => $zipPath,
                        'files_count' => $addedFiles,
                        'zip_size_mb' => round(filesize($fullZipPath) / 1048576, 2),
                    ]);

                    return $zipPath;
                } else {
                    // Remove empty ZIP
                    if (file_exists($fullZipPath)) {
                        unlink($fullZipPath);
                    }

                    return null;
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to create consolidated certificate ZIP: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Generate sanitized ZIP entry name
     */
    private function generateZipEntryName(array $file): string
    {
        $donaturName = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $file['donatur_name']));
        $donaturName = substr($donaturName, 0, 30);

        if (isset($file['certificate_number'])) {
            return "Certificate-{$file['certificate_number']}-{$donaturName}.pdf";
        } else {
            return "Certificate-{$donaturName}-{$file['donatur_id']}.pdf";
        }
    }

    protected function clearMemory(): void
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
        Log::error('Consolidated certificate generation job failed permanently', [
            'donatur_ids' => $this->donaturIds,
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
                foreach ($this->donaturIds as $donaturId) {
                    if (strpos($file, "donatur-{$donaturId}-") !== false) {
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
            'certificate:consolidated',
            'user:'.$this->userId,
            'donatur:'.count($this->donaturIds),
        ];
    }
}
