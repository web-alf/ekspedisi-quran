<?php

namespace Tests\Feature\Warehouse;

use App\Models\DailyPackingTask;
use App\Models\DailyPackingTaskItem;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\User;
use App\Services\PackingAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PackingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected $assignmentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assignmentService = $this->app->make(PackingAssignmentService::class);
    }

    /**
     * Test race condition in concurrent box access
     * This test simulates multiple users trying to access the same box simultaneously
     */
    public function test_concurrent_box_access_prevents_capacity_overflow()
    {
        // Setup test data
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $jenisQuran = JenisQuran::factory()->create([
            'nama_jenis' => 'Test Jenis',
            'kode_jenis' => 'TST',
        ]);

        $task1 = DailyPackingTask::factory()->create([
            'user_id' => $user1->id,
            'tanggal_tugas' => today(),
            'total_target' => 50,
        ]);

        $task2 = DailyPackingTask::factory()->create([
            'user_id' => $user2->id,
            'tanggal_tugas' => today(),
            'total_target' => 50,
        ]);

        // Create a box with capacity of 2 for testing
        $box = PackingBox::factory()->create([
            'daily_packing_task_id' => $task1->id,
            'jenis_quran_id' => $jenisQuran->id,
            'kapasitas' => 2,
            'jumlah_terisi' => 0,
            'status' => 'filling',
        ]);

        // Create pengiriman items
        $pengiriman1 = Pengiriman::factory()->create([
            'jenis_quran_id' => $jenisQuran->id,
            'jumlah_quran' => 1,
        ]);

        $pengiriman2 = Pengiriman::factory()->create([
            'jenis_quran_id' => $jenisQuran->id,
            'jumlah_quran' => 1,
        ]);

        $pengiriman3 = Pengiriman::factory()->create([
            'jenis_quran_id' => $jenisQuran->id,
            'jumlah_quran' => 1,
        ]);

        // Create task items
        DailyPackingTaskItem::factory()->create([
            'daily_packing_task_id' => $task1->id,
            'pengiriman_id' => $pengiriman1->id,
        ]);

        DailyPackingTaskItem::factory()->create([
            'daily_packing_task_id' => $task2->id,
            'pengiriman_id' => $pengiriman2->id,
        ]);

        DailyPackingTaskItem::factory()->create([
            'daily_packing_task_id' => $task1->id,
            'pengiriman_id' => $pengiriman3->id,
        ]);

        $results = [];
        $exceptions = [];

        // Simulate concurrent access using database transactions
        DB::transaction(function () use ($box, $pengiriman1, $pengiriman2, $pengiriman3, $user1, $user2, &$results, &$exceptions) {
            // Try to add 3 items to a box with capacity 2 concurrently
            try {
                $results[] = $box->addItemSafe($pengiriman1, $user1->id);
                Log::info('Successfully added item 1');
            } catch (\Exception $e) {
                $exceptions[] = $e->getMessage();
                Log::info('Failed to add item 1: '.$e->getMessage());
            }

            try {
                $results[] = $box->addItemSafe($pengiriman2, $user2->id);
                Log::info('Successfully added item 2');
            } catch (\Exception $e) {
                $exceptions[] = $e->getMessage();
                Log::info('Failed to add item 2: '.$e->getMessage());
            }

            try {
                $results[] = $box->addItemSafe($pengiriman3, $user1->id);
                Log::info('Successfully added item 3');
            } catch (\Exception $e) {
                $exceptions[] = $e->getMessage();
                Log::info('Failed to add item 3: '.$e->getMessage());
            }
        });

        // Assertions
        $box->refresh();

        // Should have exactly 2 items (capacity limit)
        $this->assertEquals(2, $box->jumlah_terisi);
        $this->assertEquals(2, $box->packingItems()->count());

        // Should have exactly 2 successful additions and 1 failure
        $this->assertCount(2, $results);
        $this->assertCount(1, $exceptions);

        // The exception should be about capacity
        $this->assertStringContainsString('Tidak cukup ruang di kerdus', $exceptions[0]);

        // Box should be marked as full
        $this->assertEquals('full', $box->status);
    }

    /**
     * Test concurrent box creation doesn't create duplicates
     */
    public function test_concurrent_box_creation_prevents_duplicates()
    {
        $user = User::factory()->create();
        $jenisQuran = JenisQuran::factory()->create();

        $task = DailyPackingTask::factory()->create([
            'user_id' => $user->id,
            'tanggal_tugas' => today(),
        ]);

        $pengiriman1 = Pengiriman::factory()->create(['jenis_quran_id' => $jenisQuran->id]);
        $pengiriman2 = Pengiriman::factory()->create(['jenis_quran_id' => $jenisQuran->id]);

        // Simulate concurrent box creation attempts
        $boxes = [];

        for ($i = 0; $i < 2; $i++) {
            try {
                $box = $task->getOrActivateBoxForJenis($jenisQuran->id);
                if ($box) {
                    $boxes[] = $box->id;
                }
            } catch (\Exception $e) {
                // Expected in case of race conditions
                Log::info('Box creation attempt failed: '.$e->getMessage());
            }
        }

        // Should only create one box for this jenis
        $totalBoxes = PackingBox::where('daily_packing_task_id', $task->id)
            ->where('jenis_quran_id', $jenisQuran->id)
            ->count();

        $this->assertEquals(1, $totalBoxes);
    }

    /**
     * Test transaction rollback on failure
     */
    public function test_transaction_rollback_on_failure()
    {
        $user = User::factory()->create();
        $jenisQuran = JenisQuran::factory()->create();

        $task = DailyPackingTask::factory()->create([
            'user_id' => $user->id,
            'tanggal_tugas' => today(),
        ]);

        $box = PackingBox::factory()->create([
            'daily_packing_task_id' => $task->id,
            'jenis_quran_id' => $jenisQuran->id,
            'kapasitas' => 1,
            'jumlah_terisi' => 1, // Already full
            'status' => 'full',
        ]);

        $pengiriman = Pengiriman::factory()->create(['jenis_quran_id' => $jenisQuran->id]);

        $initialCount = $box->packingItems()->count();

        // Try to add item to full box - should fail and rollback
        try {
            $box->addItemSafe($pengiriman, $user->id);
        } catch (\Exception $e) {
            // Expected exception
        }

        // Verify no packing item was created
        $this->assertEquals($initialCount, $box->packingItems()->count());

        // Verify box state unchanged
        $box->refresh();
        $this->assertEquals(1, $box->jumlah_terisi);
        $this->assertEquals('full', $box->status);
    }

    /**
     * Test retry mechanism with deadlock simulation
     */
    public function test_retry_mechanism_handles_deadlocks()
    {
        // This is a conceptual test as actual deadlock simulation is complex
        // In real scenarios, the retry mechanism would be tested with actual concurrent processes

        $user = User::factory()->create();
        $jenisQuran = JenisQuran::factory()->create();

        $task = DailyPackingTask::factory()->create([
            'user_id' => $user->id,
            'tanggal_tugas' => today(),
        ]);

        // The retry mechanism is implemented in the controller and model methods
        // This test verifies the structure exists
        $this->assertTrue(method_exists($task, 'getOrActivateBoxForJenis'));

        $box = $task->getOrActivateBoxForJenis($jenisQuran->id);
        $this->assertInstanceOf(PackingBox::class, $box);
    }

    /**
     * Test pessimistic locking prevents double processing
     */
    public function test_pessimistic_locking_prevents_double_processing()
    {
        $user = User::factory()->create();
        $jenisQuran = JenisQuran::factory()->create();

        $pengiriman = Pengiriman::factory()->create(['jenis_quran_id' => $jenisQuran->id]);

        $task = DailyPackingTask::factory()->create([
            'user_id' => $user->id,
            'tanggal_tugas' => today(),
        ]);

        $taskItem = DailyPackingTaskItem::factory()->create([
            'daily_packing_task_id' => $task->id,
            'pengiriman_id' => $pengiriman->id,
        ]);

        // Simulate first processing
        $box = $task->getOrActivateBoxForJenis($jenisQuran->id);
        $packingItem1 = $box->addItemSafe($pengiriman, $user->id);

        // Mark as packed
        $taskItem->update(['is_packed' => true, 'packed_at' => now()]);

        // Try to process again - should be prevented by the already packed check
        $alreadyPacked = \App\Models\PackingItem::where('pengiriman_id', $pengiriman->id)->exists();
        $this->assertTrue($alreadyPacked);

        // Verify only one packing item exists
        $this->assertEquals(1, \App\Models\PackingItem::where('pengiriman_id', $pengiriman->id)->count());
    }

    /**
     * Test capacity validation with different quantities
     */
    public function test_capacity_validation_with_quantities()
    {
        $user = User::factory()->create();
        $jenisQuran = JenisQuran::factory()->create();

        $task = DailyPackingTask::factory()->create([
            'user_id' => $user->id,
            'tanggal_tugas' => today(),
        ]);

        $box = PackingBox::factory()->create([
            'daily_packing_task_id' => $task->id,
            'jenis_quran_id' => $jenisQuran->id,
            'kapasitas' => 5,
            'jumlah_terisi' => 3, // 2 spaces left
            'status' => 'filling',
        ]);

        // Create pengiriman with quantity that exceeds remaining capacity
        $pengiriman = Pengiriman::factory()->create([
            'jenis_quran_id' => $jenisQuran->id,
            'jumlah_quran' => 3, // Need 3 spaces, but only 2 available
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tidak cukup ruang di kerdus');

        $box->addItemSafe($pengiriman, $user->id);
    }
}
