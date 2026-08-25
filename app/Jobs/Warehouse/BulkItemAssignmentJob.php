<?php

namespace App\Jobs\Warehouse;

use App\Jobs\Traits\TracksProgress;
use App\Models\BulkOperationLog;
use App\Models\DailyPackingTask;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkItemAssignmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TracksProgress;

    public $timeout = 3600;

    public $tries = 3;

    public $backoff = [60, 300, 900];

    protected $pengirimanIds;

    protected $targetTaskId;

    protected $userId;

    protected $assignmentType;

    protected $parameters;

    public function __construct(
        array $pengirimanIds,
        ?int $targetTaskId = null,
        string $assignmentType = 'auto_assign',
        array $parameters = [],
        ?int $userId = null
    ) {
        $this->pengirimanIds = $pengirimanIds;
        $this->targetTaskId = $targetTaskId;
        $this->assignmentType = $assignmentType;
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
                'bulk_item_assignment',
                'Assignment '.count($this->pengirimanIds)." items - {$this->assignmentType}",
                count($this->pengirimanIds),
                [
                    'assignment_type' => $this->assignmentType,
                    'target_task_id' => $this->targetTaskId,
                    'parameters' => $this->parameters,
                ]
            );

            $this->markAsProcessing();

            $startTime = microtime(true);
            $results = [
                'items_assigned' => 0,
                'items_failed' => 0,
                'boxes_created' => 0,
                'errors' => [],
            ];

            // Process items in chunks
            $itemChunks = array_chunk($this->pengirimanIds, $this->parameters['chunk_size'] ?? 50);

            foreach ($itemChunks as $chunkIndex => $itemChunk) {
                try {
                    $chunkResult = $this->processItemChunk($itemChunk);

                    $results['items_assigned'] += $chunkResult['items_assigned'];
                    $results['items_failed'] += $chunkResult['items_failed'];
                    $results['boxes_created'] += $chunkResult['boxes_created'];
                    $results['errors'] = array_merge($results['errors'], $chunkResult['errors']);

                    $this->updateProgress(
                        $results['items_assigned'],
                        $results['items_failed']
                    );

                } catch (\Exception $e) {
                    Log::error('Error processing assignment chunk: '.$e->getMessage());
                    $results['errors'][] = 'Chunk '.($chunkIndex + 1).': '.$e->getMessage();
                    $this->incrementFailed(count($itemChunk));
                }
            }

            $processingTime = round((microtime(true) - $startTime) * 1000);

            $this->markAsCompleted([
                'processing_time_ms' => $processingTime,
                'assignment_type' => $this->assignmentType,
                'items_assigned' => $results['items_assigned'],
                'items_failed' => $results['items_failed'],
                'boxes_created' => $results['boxes_created'],
                'success_rate' => count($this->pengirimanIds) > 0 ?
                    round(($results['items_assigned'] / count($this->pengirimanIds)) * 100, 2) : 0,
                'errors' => $results['errors'],
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk item assignment job failed: '.$e->getMessage());
            $this->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    protected function processItemChunk(array $pengirimanIds)
    {
        $results = [
            'items_assigned' => 0,
            'items_failed' => 0,
            'boxes_created' => 0,
            'errors' => [],
        ];

        foreach ($pengirimanIds as $pengirimanId) {
            DB::beginTransaction();
            try {
                $pengiriman = Pengiriman::with(['jenisQuran'])->find($pengirimanId);

                if (! $pengiriman) {
                    $results['errors'][] = "Pengiriman ID {$pengirimanId} tidak ditemukan";
                    $results['items_failed']++;

                    continue;
                }

                $success = false;

                switch ($this->assignmentType) {
                    case 'auto_assign':
                        $success = $this->autoAssignItem($pengiriman);
                        break;
                    case 'manual_assign':
                        $success = $this->manualAssignItem($pengiriman);
                        break;
                    case 'reassign':
                        $success = $this->reassignItem($pengiriman);
                        break;
                    default:
                        throw new \InvalidArgumentException("Unknown assignment type: {$this->assignmentType}");
                }

                if ($success) {
                    $results['items_assigned']++;

                    // Check if we created a new box
                    if (isset($this->lastCreatedBox)) {
                        $results['boxes_created']++;
                        unset($this->lastCreatedBox);
                    }
                } else {
                    $results['items_failed']++;
                    $results['errors'][] = "Failed to assign item: {$pengirimanId}";
                }

                DB::commit();

            } catch (\Exception $e) {
                DB::rollback();
                $results['items_failed']++;
                $results['errors'][] = "Error assigning item {$pengirimanId}: ".$e->getMessage();
                Log::error('Item assignment error', [
                    'pengiriman_id' => $pengirimanId,
                    'assignment_type' => $this->assignmentType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    protected function autoAssignItem(Pengiriman $pengiriman)
    {
        // Find or create appropriate task
        $task = $this->findOrCreateTask($pengiriman);

        if (! $task) {
            throw new \Exception("Could not find or create task for pengiriman {$pengiriman->id}");
        }

        // Find available box or create new one
        $box = $this->findAvailableBox($task, $pengiriman);

        if (! $box) {
            $box = $this->createNewBox($task, $pengiriman);
            $this->lastCreatedBox = true;
        }

        // Assign item to box
        return $this->assignItemToBox($pengiriman, $box);
    }

    protected function manualAssignItem(Pengiriman $pengiriman)
    {
        if (! $this->targetTaskId) {
            throw new \Exception('Target task ID required for manual assignment');
        }

        $task = DailyPackingTask::find($this->targetTaskId);
        if (! $task) {
            throw new \Exception("Target task not found: {$this->targetTaskId}");
        }

        $box = $this->findAvailableBox($task, $pengiriman);

        if (! $box) {
            $box = $this->createNewBox($task, $pengiriman);
            $this->lastCreatedBox = true;
        }

        return $this->assignItemToBox($pengiriman, $box);
    }

    protected function reassignItem(Pengiriman $pengiriman)
    {
        // Remove from current assignment if exists
        PackingItem::where('pengiriman_id', $pengiriman->id)->delete();

        // Then auto assign
        return $this->autoAssignItem($pengiriman);
    }

    protected function findOrCreateTask(Pengiriman $pengiriman)
    {
        // Find existing task for today
        $task = DailyPackingTask::where('tanggal', today())
            ->where('jenis_quran_id', $pengiriman->jenis_quran_id)
            ->where('user_id', $this->userId)
            ->first();

        if (! $task) {
            // Create new task
            $task = DailyPackingTask::create([
                'tanggal' => today(),
                'user_id' => $this->userId,
                'jenis_quran_id' => $pengiriman->jenis_quran_id,
                'target_quantity' => $this->parameters['default_target'] ?? 100,
                'status' => 'active',
            ]);
        }

        return $task;
    }

    protected function findAvailableBox(DailyPackingTask $task, Pengiriman $pengiriman)
    {
        return PackingBox::where('daily_packing_task_id', $task->id)
            ->where('jenis_quran_id', $pengiriman->jenis_quran_id)
            ->where('status', 'open')
            ->whereRaw('jumlah_terisi < kapasitas')
            ->orderBy('jumlah_terisi', 'desc') // Fill boxes that are more full first
            ->first();
    }

    protected function createNewBox(DailyPackingTask $task, Pengiriman $pengiriman)
    {
        $boxCode = $this->generateBoxCode($task, $pengiriman);
        $capacity = $this->parameters['box_capacity'] ?? 50;

        $box = PackingBox::create([
            'kode_kerdus' => $boxCode,
            'daily_packing_task_id' => $task->id,
            'jenis_quran_id' => $pengiriman->jenis_quran_id,
            'kapasitas' => $capacity,
            'jumlah_terisi' => 0,
            'status' => 'open',
        ]);

        Log::info('Created new box', [
            'box_code' => $boxCode,
            'task_id' => $task->id,
            'jenis_quran_id' => $pengiriman->jenis_quran_id,
        ]);

        return $box;
    }

    protected function assignItemToBox(Pengiriman $pengiriman, PackingBox $box)
    {
        // Check if already assigned
        $existing = PackingItem::where('pengiriman_id', $pengiriman->id)->first();
        if ($existing) {
            return true; // Already assigned
        }

        // Check box capacity
        if ($box->jumlah_terisi >= $box->kapasitas) {
            throw new \Exception("Box {$box->kode_kerdus} is full");
        }

        // Create packing item
        PackingItem::create([
            'packing_box_id' => $box->id,
            'pengiriman_id' => $pengiriman->id,
            'packed_at' => now(),
            'packed_by' => $this->userId,
        ]);

        // Update box count
        $box->increment('jumlah_terisi');

        // Log operation
        BulkOperationLog::logOperation([
            'operation_type' => 'item_assignment',
            'box_code' => $box->kode_kerdus,
            'pengiriman_ids' => [$pengiriman->id],
            'notes' => "Bulk assignment - {$this->assignmentType}",
        ]);

        return true;
    }

    protected function generateBoxCode(DailyPackingTask $task, Pengiriman $pengiriman)
    {
        $date = now()->format('Ymd');
        $jenisCode = $pengiriman->jenisQuran->kode ?? 'UNK';
        $userCode = str_pad($task->user_id, 2, '0', STR_PAD_LEFT);

        // Find next sequence number
        $lastBox = PackingBox::where('kode_kerdus', 'like', "KB-{$date}-%")
            ->orderBy('kode_kerdus', 'desc')
            ->first();

        $sequence = 1;
        if ($lastBox) {
            preg_match('/KB-\d{8}-(\d{3})/', $lastBox->kode_kerdus, $matches);
            $sequence = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
        }

        $sequenceStr = str_pad($sequence, 3, '0', STR_PAD_LEFT);

        return "KB-{$date}-{$sequenceStr}-{$jenisCode}-{$userCode}";
    }
}
