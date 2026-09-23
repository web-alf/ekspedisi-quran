<?php

namespace App\Services;

use App\Models\DailyPackingTask;
use App\Models\DailyPackingTaskTarget;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\PackingNotification;
use App\Models\SharedBoxAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BoxBasedAssignmentService
{
    /**
     * Assignment method constants
     */
    const METHOD_BOX_BASED = 'mixed_box_based';

    const BOX_TYPE_INDIVIDUAL = 'individual';

    const BOX_TYPE_SHARED = 'shared';

    /**
     * Assign boxes to users with detailed breakdown
     */
    public function assignBoxBasedTarget(array $assignments, $date = null): array
    {
        $date = $date ?: today();
        $results = [
            'total_boxes' => 0,
            'total_items' => 0,
            'individual_boxes' => [],
            'shared_boxes' => [],
            'user_assignments' => [],
        ];

        DB::transaction(function () use ($assignments, $date, &$results) {
            foreach ($assignments as $assignment) {
                $result = $this->processUserAssignment($assignment, $date);

                $results['total_boxes'] += $result['total_boxes'];
                $results['total_items'] += $result['total_items'];
                $results['individual_boxes'] = array_merge($results['individual_boxes'], $result['individual_boxes']);
                $results['shared_boxes'] = array_merge($results['shared_boxes'], $result['shared_boxes']);
                $results['user_assignments'][$assignment['user_id']] = $result['user_summary'];
            }
        });

        Log::info('Box-based assignment completed', [
            'total_boxes' => $results['total_boxes'],
            'total_items' => $results['total_items'],
            'users_count' => count($results['user_assignments']),
            'date' => $date->format('Y-m-d'),
        ]);

        return $results;
    }

    /**
     * Process individual user assignment
     */
    private function processUserAssignment(array $assignment, Carbon $date): array
    {
        $userId = $assignment['user_id'];
        $user = User::find($userId);

        if (! $user) {
            throw new \Exception("User not found: {$userId}");
        }

        // Check if task already exists (with lock - race-safe)
        // assignBoxBasedTarget membungkus ini dalam DB::transaction, jadi lockForUpdate
        // membuat dua panggilan paralel saling menunggu, mencegah duplicate task.
        $existingTask = DailyPackingTask::where('user_id', $userId)
            ->whereDate('tanggal_tugas', $date)
            ->lockForUpdate()
            ->first();

        if ($existingTask) {
            throw new \Exception("Task already exists for user {$user->name} on {$date->format('Y-m-d')}");
        }

        // Calculate totals
        $totalBoxes = 0;
        $totalItems = 0;
        $hasSharedBoxes = false;

        // Calculate from individual boxes
        foreach ($assignment['individual_boxes'] ?? [] as $boxConfig) {
            $totalBoxes += $boxConfig['box_count'];
            $totalItems += $boxConfig['box_count'] * $boxConfig['capacity'];
        }

        // Calculate from shared box allocations
        foreach ($assignment['shared_allocations'] ?? [] as $allocation) {
            $hasSharedBoxes = true;
            $totalItems += $allocation['allocated_items'];
        }

        // Create daily packing task
        $task = DailyPackingTask::create([
            'user_id' => $userId,
            'tanggal_tugas' => $date,
            'total_target' => $totalItems,
            'assignment_method' => self::METHOD_BOX_BASED,
            'box_breakdown' => $assignment,
            'has_shared_boxes' => $hasSharedBoxes,
            'total_boxes_assigned' => $totalBoxes,
            'assigned_by' => auth()->id(),
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);

        $result = [
            'task_id' => $task->id,
            'total_boxes' => $totalBoxes,
            'total_items' => $totalItems,
            'individual_boxes' => [],
            'shared_boxes' => [],
            'user_summary' => [
                'user_id' => $userId,
                'user_name' => $user->name,
                'total_target' => $totalItems,
                'total_boxes' => $totalBoxes,
                'has_shared' => $hasSharedBoxes,
            ],
        ];

        // Create individual boxes
        foreach ($assignment['individual_boxes'] ?? [] as $boxConfig) {
            $boxes = $this->createIndividualBoxes($task, $boxConfig, $date);
            $result['individual_boxes'] = array_merge($result['individual_boxes'], $boxes);
        }

        // Create shared box assignments
        foreach ($assignment['shared_allocations'] ?? [] as $allocation) {
            $sharedAssignment = $this->createSharedBoxAssignment($task, $allocation);
            $result['shared_boxes'][] = $sharedAssignment;
        }

        // Create target breakdowns
        $this->createTargetBreakdowns($task, $assignment);

        // Send assignment notification
        $this->sendBoxBasedNotification($task, $result);

        return $result;
    }

    /**
     * Create individual boxes for a user
     */
    private function createIndividualBoxes(DailyPackingTask $task, array $boxConfig, Carbon $date): array
    {
        $jenis = JenisQuran::find($boxConfig['jenis_id']);
        if (! $jenis) {
            throw new \Exception("Jenis Quran not found: {$boxConfig['jenis_id']}");
        }

        $boxes = [];
        $dateStr = $date->format('Ymd');
        $userIdPadded = str_pad($task->user_id, 3, '0', STR_PAD_LEFT);

        for ($i = 1; $i <= $boxConfig['box_count']; $i++) {
            $sequence = str_pad($i, 2, '0', STR_PAD_LEFT);
            $boxCode = "KB-{$dateStr}-{$userIdPadded}-{$jenis->kode_jenis}-{$sequence}";

            // Check for unique code (with lock - race-safe)
            // assignBoxBasedTarget membungkus ini dalam DB::transaction, jadi lockForUpdate
            // membuat dua proses paralel saling menunggu, mencegah duplicate box code.
            $attempts = 0;
            $maxAttempts = 5;
            while ($attempts < $maxAttempts) {
                $existing = PackingBox::where('kode_kerdus', $boxCode)->lockForUpdate()->first();
                if (! $existing) {
                    break;
                }

                $attempts++;
                $sequence = str_pad($i + $attempts, 2, '0', STR_PAD_LEFT);
                $boxCode = "KB-{$dateStr}-{$userIdPadded}-{$jenis->kode_jenis}-{$sequence}";
            }

            if ($attempts >= $maxAttempts) {
                throw new \Exception("Cannot generate unique box code after {$maxAttempts} attempts");
            }

            $box = PackingBox::create([
                'daily_packing_task_id' => $task->id,
                'kode_kerdus' => $boxCode,
                'jenis_quran_id' => $jenis->id,
                'assigned_user_id' => $task->user_id,
                'assignment_type' => self::BOX_TYPE_INDIVIDUAL,
                'is_shared_box' => false,
                'created_by_supervisor' => auth()->id(),
                'target_completion_date' => $date,
                'kapasitas' => $jenis->getDefaultCapacity(),
                'jumlah_terisi' => 0,
                'status' => 'empty',
                'box_metadata' => [
                    'assignment_sequence' => $i,
                    'created_via' => 'box_based_assignment',
                ],
            ]);

            $boxes[] = [
                'box_id' => $box->id,
                'box_code' => $boxCode,
                'jenis_code' => $jenis->kode_jenis,
                'jenis_name' => $jenis->nama_jenis,
                'capacity' => $box->kapasitas,
                'user_id' => $task->user_id,
                'user_name' => $task->user->name,
            ];
        }

        return $boxes;
    }

    /**
     * Create shared box assignment
     */
    private function createSharedBoxAssignment(DailyPackingTask $task, array $allocation): array
    {
        // Find or create the shared box (with lock - race-safe)
        // assignBoxBasedTarget membungkus ini dalam DB::transaction, jadi lockForUpdate
        // membuat dua proses paralel saling menunggu, mencegah duplicate box.
        $sharedBox = PackingBox::where('kode_kerdus', $allocation['box_code'])
            ->lockForUpdate()
            ->first();

        if (! $sharedBox) {
            // Create the shared box if it doesn't exist
            $jenis = JenisQuran::find($allocation['jenis_id']);
            if (! $jenis) {
                throw new \Exception("Jenis Quran not found for ID: {$allocation['jenis_id']}");
            }

            $sharedBox = PackingBox::create([
                'daily_packing_task_id' => $task->id,
                'kode_kerdus' => $allocation['box_code'],
                'jenis_quran_id' => $allocation['jenis_id'],
                'assignment_type' => self::BOX_TYPE_SHARED,
                'is_shared_box' => true,
                'created_by_supervisor' => auth()->id(),
                'target_completion_date' => $task->tanggal_tugas,
                'kapasitas' => $jenis->getDefaultCapacity(),
                'jumlah_terisi' => 0,
                'status' => 'empty',
                'box_metadata' => [
                    'created_via' => 'box_based_assignment',
                ],
            ]);
        }

        // Create assignment
        $assignment = SharedBoxAssignment::create([
            'packing_box_id' => $sharedBox->id,
            'daily_packing_task_id' => $task->id,
            'user_id' => $task->user_id,
            'allocated_items' => $allocation['allocated_items'],
            'completed_items' => 0,
            'progress_metadata' => [
                'assigned_via' => 'box_based_assignment',
                'assignment_timestamp' => now()->toISOString(),
            ],
        ]);

        return [
            'assignment_id' => $assignment->id,
            'box_code' => $sharedBox->kode_kerdus,
            'allocated_items' => $allocation['allocated_items'],
            'user_id' => $task->user_id,
            'user_name' => $task->user->name,
        ];
    }

    /**
     * Create target breakdowns for detailed tracking
     */
    private function createTargetBreakdowns(DailyPackingTask $task, array $assignment): void
    {
        // Individual box breakdowns
        foreach ($assignment['individual_boxes'] ?? [] as $boxConfig) {
            $targetQuantity = $boxConfig['box_count'] * $boxConfig['capacity'];

            DailyPackingTaskTarget::create([
                'daily_packing_task_id' => $task->id,
                'jenis_quran_id' => $boxConfig['jenis_id'],
                'target_boxes' => $boxConfig['box_count'],
                'target_quantity' => $targetQuantity,
                'completed_quantity' => 0,
                'box_capacity' => $boxConfig['capacity'],
                'is_shared_box' => false,
                'assignment_metadata' => [
                    'assignment_type' => 'individual_boxes',
                    'box_configs' => $boxConfig,
                ],
            ]);
        }

        // Shared box breakdowns
        foreach ($assignment['shared_allocations'] ?? [] as $allocation) {
            $jenis = JenisQuran::where('kode_jenis', $allocation['jenis_code'])->first();

            DailyPackingTaskTarget::create([
                'daily_packing_task_id' => $task->id,
                'jenis_quran_id' => $jenis->id,
                'target_boxes' => 0,
                'target_quantity' => $allocation['allocated_items'],
                'completed_quantity' => 0,
                'box_capacity' => $jenis->getDefaultCapacity(),
                'is_shared_box' => true,
                'shared_box_code' => $allocation['box_code'],
                'assignment_metadata' => [
                    'assignment_type' => 'shared_allocation',
                    'allocation_config' => $allocation,
                ],
            ]);
        }
    }

    /**
     * Send notification about box-based assignment
     */
    private function sendBoxBasedNotification(DailyPackingTask $task, array $result): void
    {
        $individualBoxesText = '';
        if (! empty($result['individual_boxes'])) {
            $boxCodes = collect($result['individual_boxes'])->pluck('box_code')->take(3)->implode(', ');
            $moreCount = count($result['individual_boxes']) - 3;
            $individualBoxesText = "📦 Personal boxes: {$boxCodes}".($moreCount > 0 ? " (+{$moreCount} more)" : '');
        }

        $sharedBoxesText = '';
        if (! empty($result['shared_boxes'])) {
            $sharedBoxCodes = collect($result['shared_boxes'])->pluck('box_code')->take(2)->implode(', ');
            $sharedBoxesText = "🤝 Shared boxes: {$sharedBoxCodes}";
        }

        $message = "✅ Box assignment berhasil dibuat!\n\n";
        $message .= "🎯 Total target: {$result['total_items']} item\n";
        $message .= "📦 Total boxes: {$result['total_boxes']}\n\n";

        if ($individualBoxesText) {
            $message .= "{$individualBoxesText}\n";
        }
        if ($sharedBoxesText) {
            $message .= "{$sharedBoxesText}\n";
        }

        $message .= "\n📱 Buka dashboard untuk detail lengkap.";

        PackingNotification::create([
            'user_id' => $task->user_id,
            'daily_packing_task_id' => $task->id,
            'type' => 'assignment',
            'level' => 'info',
            'title' => 'Box Assignment Ready',
            'message' => $message,
            'meta_data' => [
                'assignment_method' => 'box_based',
                'total_boxes' => $result['total_boxes'],
                'total_items' => $result['total_items'],
                'has_individual' => ! empty($result['individual_boxes']),
                'has_shared' => ! empty($result['shared_boxes']),
            ],
        ]);
    }

    /**
     * Get next sequence number for shared box
     */
    private function getNextSharedBoxSequence(Carbon $date, string $jenisCode): string
    {
        $dateStr = $date->format('Ymd');
        $prefix = "SB-{$dateStr}-{$jenisCode}-";

        $lastBox = PackingBox::where('kode_kerdus', 'like', $prefix.'%')
            ->orderBy('kode_kerdus', 'desc')
            ->first();

        if ($lastBox) {
            preg_match('/SB-\\d{8}-[A-Z0-9]+-(\\d{2})$/', $lastBox->kode_kerdus, $matches);
            $lastSequence = isset($matches[1]) ? intval($matches[1]) : 0;

            return str_pad($lastSequence + 1, 2, '0', STR_PAD_LEFT);
        }

        return '01';
    }
}
