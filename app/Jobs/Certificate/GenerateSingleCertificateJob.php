<?php

namespace App\Jobs\Certificate;

use App\Jobs\Traits\TracksProgress;
use App\Models\WakafBatch;
use App\Services\BatchCertificateService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateSingleCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TracksProgress;

    public $timeout = 1800; // 30 minutes

    public $tries = 3;

    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    protected $wakafBatchId;

    protected $options;

    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $wakafBatchId, array $options = [], ?int $userId = null)
    {
        $this->wakafBatchId = $wakafBatchId;
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
        try {
            $wakafBatch = WakafBatch::with(['donatur', 'jenisQuran', 'sertifikat'])->findOrFail($this->wakafBatchId);

            $this->initializeProgress(
                'single_certificate_generation',
                "Generate sertifikat untuk batch {$wakafBatch->batch_code}",
                1,
                [
                    'wakaf_batch_id' => $this->wakafBatchId,
                    'batch_code' => $wakafBatch->batch_code,
                    'donatur_name' => $wakafBatch->donatur->nama_donatur,
                    'options' => $this->options,
                ]
            );

            $this->markAsProcessing();

            $startTime = microtime(true);

            Log::info('Starting single certificate generation', [
                'wakaf_batch_id' => $this->wakafBatchId,
                'batch_code' => $wakafBatch->batch_code,
                'user_id' => $this->userId,
            ]);

            // Generate the certificate
            $sertifikat = $certificateService->generateBatchCertificate($wakafBatch, $this->options);

            $processingTime = round((microtime(true) - $startTime) * 1000);

            // Prepare result data
            $results = [
                'processing_time_ms' => $processingTime,
                'certificate_id' => $sertifikat->id,
                'certificate_number' => $sertifikat->nomor_sertifikat,
                'file_path' => $sertifikat->file_path ?? null,
                'donatur_name' => $wakafBatch->donatur->nama_donatur,
                'batch_code' => $wakafBatch->batch_code,
                'download_url' => route('admin.certificates.download', $sertifikat->id),
                'preview_url' => route('admin.certificates.preview', $sertifikat->id),
                'wakif_count' => $certificateService->getWakifItemsForBatch($wakafBatch)->groupBy('wakif_name')->count(),
            ];

            $this->updateProgress(1, 0);

            $this->markAsCompleted($results);

            Log::info('Single certificate generation completed', $results);

        } catch (Exception $e) {
            Log::error('Single certificate generation failed', [
                'wakaf_batch_id' => $this->wakafBatchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception)
    {
        Log::error('Single certificate generation job failed permanently', [
            'wakaf_batch_id' => $this->wakafBatchId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Cleanup any partial files if they exist
        $this->cleanupPartialFiles();
    }

    /**
     * Clean up any partial files created during failed processing
     */
    private function cleanupPartialFiles()
    {
        try {
            // Check if any temporary files were created and clean them up
            $tempPattern = "certificates/temp/batch-{$this->wakafBatchId}-*.pdf";
            $tempFiles = Storage::files('certificates/temp');

            foreach ($tempFiles as $file) {
                if (strpos($file, "batch-{$this->wakafBatchId}-") !== false) {
                    Storage::delete($file);
                    Log::info('Cleaned up partial certificate file', ['file' => $file]);
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to cleanup partial files', [
                'wakaf_batch_id' => $this->wakafBatchId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags()
    {
        return [
            'certificate:single',
            'wakaf_batch:'.$this->wakafBatchId,
            'user:'.$this->userId,
        ];
    }
}
