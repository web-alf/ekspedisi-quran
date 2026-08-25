<?php

namespace App\Jobs\Warehouse;

use App\Jobs\Traits\TracksProgress;
use App\Models\BulkOperationLog;
use App\Models\PackingBox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkBoxProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TracksProgress;

    public $timeout = 3600;

    public $tries = 3;

    public $backoff = [60, 300, 900];

    protected $boxCodes;

    protected $operation;

    protected $parameters;

    protected $userId;

    public function __construct(array $boxCodes, string $operation, array $parameters = [], ?int $userId = null)
    {
        $this->boxCodes = $boxCodes;
        $this->operation = $operation;
        $this->parameters = $parameters;
        $this->userId = $userId ?? auth()->id();

        // Use dedicated warehouse queue with connection
        $this->onConnection('redis-warehouse')
            ->onQueue('warehouse');
    }

    public function handle()
    {
        try {
            $this->initializeProgress(
                'bulk_box_processing',
                'Pemrosesan bulk untuk '.count($this->boxCodes)." box - {$this->operation}",
                count($this->boxCodes),
                [
                    'operation' => $this->operation,
                    'parameters' => $this->parameters,
                ]
            );

            $this->markAsProcessing();

            $startTime = microtime(true);
            $results = [
                'boxes_processed' => 0,
                'boxes_failed' => 0,
                'errors' => [],
            ];

            // Process boxes in chunks
            $boxChunks = array_chunk($this->boxCodes, 20);

            foreach ($boxChunks as $chunkIndex => $boxChunk) {
                try {
                    $chunkResult = $this->processBoxChunk($boxChunk);

                    $results['boxes_processed'] += $chunkResult['boxes_processed'];
                    $results['boxes_failed'] += $chunkResult['boxes_failed'];
                    $results['errors'] = array_merge($results['errors'], $chunkResult['errors']);

                    $this->updateProgress(
                        $results['boxes_processed'],
                        $results['boxes_failed']
                    );

                } catch (\Exception $e) {
                    Log::error('Error processing box chunk: '.$e->getMessage());
                    $results['errors'][] = 'Chunk '.($chunkIndex + 1).': '.$e->getMessage();
                    $this->incrementFailed(count($boxChunk));
                }
            }

            $processingTime = round((microtime(true) - $startTime) * 1000);

            $this->markAsCompleted([
                'processing_time_ms' => $processingTime,
                'operation' => $this->operation,
                'boxes_processed' => $results['boxes_processed'],
                'boxes_failed' => $results['boxes_failed'],
                'success_rate' => count($this->boxCodes) > 0 ?
                    round(($results['boxes_processed'] / count($this->boxCodes)) * 100, 2) : 0,
                'errors' => $results['errors'],
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk box processing job failed: '.$e->getMessage());
            $this->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    protected function processBoxChunk(array $boxCodes)
    {
        $results = [
            'boxes_processed' => 0,
            'boxes_failed' => 0,
            'errors' => [],
        ];

        foreach ($boxCodes as $boxCode) {
            try {
                $success = false;

                switch ($this->operation) {
                    case 'seal':
                        $success = $this->sealBox($boxCode);
                        break;
                    case 'unseal':
                        $success = $this->unsealBox($boxCode);
                        break;
                    case 'validate':
                        $success = $this->validateBox($boxCode);
                        break;
                    case 'archive':
                        $success = $this->archiveBox($boxCode);
                        break;
                    default:
                        throw new \InvalidArgumentException("Unknown operation: {$this->operation}");
                }

                if ($success) {
                    $results['boxes_processed']++;
                } else {
                    $results['boxes_failed']++;
                    $results['errors'][] = "Failed to {$this->operation} box: {$boxCode}";
                }

            } catch (\Exception $e) {
                $results['boxes_failed']++;
                $results['errors'][] = "Error {$this->operation} box {$boxCode}: ".$e->getMessage();
                Log::error('Box processing error', [
                    'operation' => $this->operation,
                    'box_code' => $boxCode,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    protected function sealBox(string $boxCode)
    {
        return DB::transaction(function () use ($boxCode) {
            $box = PackingBox::where('kode_kerdus', $boxCode)->first();

            if (! $box) {
                throw new \Exception("Box not found: {$boxCode}");
            }

            if ($box->status === 'sealed') {
                return true; // Already sealed
            }

            $sealCode = $this->parameters['seal_code'] ?? $this->generateSealCode();

            $box->update([
                'status' => 'sealed',
                'seal_code' => $sealCode,
                'sealed_at' => now(),
            ]);

            BulkOperationLog::logOperation([
                'operation_type' => 'box_seal',
                'box_code' => $boxCode,
                'box_seal_code' => $sealCode,
                'pengiriman_ids' => $box->packingItems->pluck('pengiriman_id')->toArray(),
                'notes' => 'Bulk seal operation',
            ]);

            return true;
        });
    }

    protected function unsealBox(string $boxCode)
    {
        return DB::transaction(function () use ($boxCode) {
            $box = PackingBox::where('kode_kerdus', $boxCode)->first();

            if (! $box) {
                throw new \Exception("Box not found: {$boxCode}");
            }

            $box->update([
                'status' => 'open',
                'seal_code' => null,
                'sealed_at' => null,
            ]);

            BulkOperationLog::logOperation([
                'operation_type' => 'box_unseal',
                'box_code' => $boxCode,
                'pengiriman_ids' => $box->packingItems->pluck('pengiriman_id')->toArray(),
                'notes' => 'Bulk unseal operation',
            ]);

            return true;
        });
    }

    protected function validateBox(string $boxCode)
    {
        $box = PackingBox::with(['packingItems.pengiriman'])->where('kode_kerdus', $boxCode)->first();

        if (! $box) {
            throw new \Exception("Box not found: {$boxCode}");
        }

        // Validation logic
        $issues = [];

        if ($box->jumlah_terisi > $box->kapasitas) {
            $issues[] = 'Box over capacity';
        }

        if ($box->packingItems->isEmpty()) {
            $issues[] = 'Box is empty';
        }

        // Check for duplicate items
        $resiNumbers = $box->packingItems->pluck('pengiriman.no_resi')->filter();
        if ($resiNumbers->count() !== $resiNumbers->unique()->count()) {
            $issues[] = 'Duplicate resi numbers';
        }

        if (! empty($issues)) {
            throw new \Exception('Validation failed: '.implode(', ', $issues));
        }

        return true;
    }

    protected function archiveBox(string $boxCode)
    {
        return DB::transaction(function () use ($boxCode) {
            $box = PackingBox::where('kode_kerdus', $boxCode)->first();

            if (! $box) {
                throw new \Exception("Box not found: {$boxCode}");
            }

            $box->update([
                'status' => 'archived',
                'archived_at' => now(),
            ]);

            BulkOperationLog::logOperation([
                'operation_type' => 'box_archive',
                'box_code' => $boxCode,
                'pengiriman_ids' => $box->packingItems->pluck('pengiriman_id')->toArray(),
                'notes' => 'Bulk archive operation',
            ]);

            return true;
        });
    }

    protected function generateSealCode()
    {
        return 'SEAL-'.now()->format('Ymd').'-'.strtoupper(substr(md5(uniqid()), 0, 6));
    }
}
