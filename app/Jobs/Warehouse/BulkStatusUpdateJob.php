<?php

namespace App\Jobs\Warehouse;

use App\Jobs\Traits\TracksProgress;
use App\Models\BulkOperationLog;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\StatusHistory;
use App\Models\StatusPengiriman;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BulkStatusUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TracksProgress;

    public $timeout = 3600; // 1 hour timeout

    public $tries = 3;

    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    protected $boxIds;

    protected $newStatusId;

    protected $address;

    protected $notes;

    protected $location;

    protected $documentationPath;

    protected $userId;

    protected $chunkSize;

    public function __construct(
        array $boxIds,
        int $newStatusId,
        ?string $address = null,
        ?string $notes = null,
        ?string $location = null,
        ?string $documentationPath = null,
        ?int $userId = null,
        int $chunkSize = 100
    ) {
        $this->boxIds = $boxIds;
        $this->newStatusId = $newStatusId;
        $this->address = $address;
        $this->notes = $notes;
        $this->location = $location;
        $this->documentationPath = $documentationPath;
        $this->userId = $userId ?? auth()->id();
        $this->chunkSize = $chunkSize;

        // Use dedicated warehouse queue with connection
        $this->onConnection('redis-warehouse')
            ->onQueue('warehouse');
    }

    public function handle()
    {
        try {
            // Calculate total items first
            $totalItems = $this->calculateTotalItems();

            // Initialize progress tracking
            $newStatus = StatusPengiriman::find($this->newStatusId);
            $this->initializeProgress(
                'bulk_status_update',
                "Update status ke {$newStatus->nama} untuk ".count($this->boxIds).' box',
                $totalItems,
                [
                    'box_count' => count($this->boxIds),
                    'new_status_id' => $this->newStatusId,
                    'new_status_name' => $newStatus->nama,
                    'has_address' => ! empty($this->address),
                    'has_documentation' => ! empty($this->documentationPath),
                ]
            );

            $this->markAsProcessing();

            $startTime = microtime(true);
            $results = [
                'boxes_processed' => 0,
                'items_updated' => 0,
                'items_failed' => 0,
                'errors' => [],
            ];

            // Process boxes in chunks
            $boxChunks = array_chunk($this->boxIds, 10); // Process 10 boxes at a time

            foreach ($boxChunks as $chunkIndex => $boxChunk) {
                try {
                    $chunkResult = $this->processBoxChunk($boxChunk, $newStatus);

                    $results['boxes_processed'] += $chunkResult['boxes_processed'];
                    $results['items_updated'] += $chunkResult['items_updated'];
                    $results['items_failed'] += $chunkResult['items_failed'];
                    $results['errors'] = array_merge($results['errors'], $chunkResult['errors']);

                    // Update progress
                    $this->updateProgress(
                        $results['items_updated'],
                        $results['items_failed']
                    );

                    Log::info('Processed box chunk', [
                        'chunk_index' => $chunkIndex + 1,
                        'total_chunks' => count($boxChunks),
                        'chunk_size' => count($boxChunk),
                        'items_updated' => $chunkResult['items_updated'],
                    ]);

                } catch (\Exception $e) {
                    Log::error('Error processing box chunk: '.$e->getMessage());
                    $results['errors'][] = 'Chunk '.($chunkIndex + 1).': '.$e->getMessage();
                    $this->incrementFailed(count($boxChunk));
                }

                // Prevent memory buildup
                if ($chunkIndex % 5 === 0) {
                    $this->clearMemory();
                }
            }

            $processingTime = round((microtime(true) - $startTime) * 1000);

            // Mark as completed with results
            $this->markAsCompleted([
                'processing_time_ms' => $processingTime,
                'boxes_processed' => $results['boxes_processed'],
                'items_updated' => $results['items_updated'],
                'items_failed' => $results['items_failed'],
                'success_rate' => $totalItems > 0 ? round(($results['items_updated'] / $totalItems) * 100, 2) : 0,
                'errors' => $results['errors'],
                'new_status' => $newStatus->nama,
            ]);

            Log::info('Bulk status update completed', [
                'job_id' => $this->job->getJobId(),
                'boxes_processed' => $results['boxes_processed'],
                'items_updated' => $results['items_updated'],
                'processing_time_ms' => $processingTime,
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk status update job failed: '.$e->getMessage());
            $this->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    protected function calculateTotalItems()
    {
        return PackingBox::whereIn('id', $this->boxIds)
            ->withCount('packingItems')
            ->get()
            ->sum('packing_items_count');
    }

    protected function processBoxChunk(array $boxIds, StatusPengiriman $newStatus)
    {
        $results = [
            'boxes_processed' => 0,
            'items_updated' => 0,
            'items_failed' => 0,
            'errors' => [],
        ];

        foreach ($boxIds as $boxId) {
            DB::beginTransaction();
            try {
                $box = PackingBox::with('packingItems.pengiriman.status')->find($boxId);

                if (! $box) {
                    $results['errors'][] = "Box ID {$boxId} tidak ditemukan";

                    continue;
                }

                $pengirimanItems = $box->packingItems;
                $pengirimanIds = $pengirimanItems->pluck('pengiriman_id')->toArray();

                if (empty($pengirimanIds)) {
                    $results['errors'][] = "Box {$box->kode_kerdus} tidak memiliki item";

                    continue;
                }

                // Store old statuses for history
                $oldStatuses = [];
                foreach ($pengirimanItems as $item) {
                    $oldStatuses[$item->pengiriman_id] = $item->pengiriman->status_id;
                }

                // Prepare update data
                $updateData = ['status_id' => $this->newStatusId];
                if ($this->address) {
                    $updateData['alamat_tujuan'] = $this->address;
                }

                // Update all pengiriman status
                $updated = Pengiriman::whereIn('id', $pengirimanIds)->update($updateData);

                // Create status history for each pengiriman
                foreach ($pengirimanIds as $pengirimanId) {
                    $oldStatusId = $oldStatuses[$pengirimanId] ?? null;

                    if ($oldStatusId != $this->newStatusId) {
                        $historyData = [
                            'pengiriman_id' => $pengirimanId,
                            'status_from' => $oldStatusId,
                            'status_to' => $this->newStatusId,
                            'catatan' => 'Bulk update Job: '.$box->kode_kerdus.
                                        ($this->notes ? ' - '.$this->notes : ''),
                            'created_by' => $this->userId,
                        ];

                        if ($this->location) {
                            $historyData['lokasi'] = $this->location;
                        }

                        if ($this->documentationPath) {
                            $historyData['dokumentasi'] = [
                                [
                                    'path' => $this->documentationPath,
                                    'url' => Storage::url($this->documentationPath),
                                ],
                            ];
                        }

                        StatusHistory::create($historyData);
                    }
                }

                // Log bulk operation
                BulkOperationLog::create([
                    'operation_type' => 'status_update',
                    'box_code' => $box->kode_kerdus,
                    'box_seal_code' => $box->seal_code,
                    'user_id' => $this->userId,
                    'items_count' => count($pengirimanIds),
                    'pengiriman_ids' => $pengirimanIds,
                    'new_status_id' => $this->newStatusId,
                    'new_address' => $this->address,
                    'notes' => $this->notes,
                    'ip_address' => request()->ip() ?? 'queue-job',
                    'user_agent' => 'Queue Job',
                    'operation_timestamp' => now(),
                ]);

                DB::commit();

                $results['boxes_processed']++;
                $results['items_updated'] += $updated;

            } catch (\Exception $e) {
                DB::rollback();
                $errorMsg = "Error processing box {$boxId}: ".$e->getMessage();
                Log::error($errorMsg);
                $results['errors'][] = $errorMsg;
                $results['items_failed'] += count($pengirimanIds ?? []);
            }
        }

        return $results;
    }

    protected function clearMemory()
    {
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    public function retryUntil()
    {
        return now()->addHours(24);
    }
}
