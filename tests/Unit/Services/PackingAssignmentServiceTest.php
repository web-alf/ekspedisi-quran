<?php

namespace Tests\Unit\Services;

use App\Models\DailyPackingTask;
use App\Models\User;
use App\Services\PackingAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackingAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PackingAssignmentService $service;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $this->service = app(PackingAssignmentService::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('warehouse');
    }

    public function test_can_assign_daily_task_with_custom_target()
    {
        $result = $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            50
        );

        $this->assertEquals('manual_assigned', $result['status']);
        $this->assertEquals(50, $result['total_target']);

        $task = DailyPackingTask::find($result['task_id']);
        $this->assertEquals(50, $task->total_target);
        $this->assertEquals($this->user->id, $task->user_id);
    }

    public function test_throws_exception_when_assigning_duplicate_task_without_allow_update()
    {
        // First assignment
        $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            50
        );

        // Second assignment without allowUpdate should throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Task sudah ada untuk user ini pada tanggal tersebut');

        $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            75,
            false
        );
    }

    public function test_can_update_existing_task_when_allow_update_is_true()
    {
        // First assignment
        $firstResult = $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            50
        );

        $this->assertEquals('manual_assigned', $firstResult['status']);
        $this->assertEquals(50, $firstResult['total_target']);

        // Update with allowUpdate = true
        $updateResult = $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            75,
            true // Allow update
        );

        $this->assertEquals('target_updated', $updateResult['status']);
        $this->assertEquals(50, $updateResult['old_target']);
        $this->assertEquals(75, $updateResult['new_target']);
        $this->assertEquals(75, $updateResult['new_base_target']);

        // Verify in database
        $task = DailyPackingTask::find($updateResult['task_id']);
        $this->assertEquals(75, $task->total_target);
    }

    public function test_prevents_updating_target_below_completed_amount()
    {
        // Create task
        $result = $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            100
        );

        $task = DailyPackingTask::find($result['task_id']);

        // Simulate partial completion
        $task->update(['total_selesai' => 60]);

        // Try to update target below completed amount
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Target baru tidak boleh lebih kecil dari yang sudah diselesaikan');

        $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            50, // Less than 60 completed
            true
        );
    }

    public function test_prevents_updating_completed_task()
    {
        // Create and complete task
        $result = $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            50
        );

        $task = DailyPackingTask::find($result['task_id']);
        $task->update([
            'status' => DailyPackingTask::STATUS_COMPLETED,
            'total_selesai' => 50,
        ]);

        // Try to update completed task
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Task sudah selesai, tidak dapat diubah targetnya');

        $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            75,
            true
        );
    }

    public function test_sends_notification_when_updating_target()
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');
        $this->actingAs($supervisor);

        // Create initial task
        $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            50
        );

        // Update task
        $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            75,
            true
        );

        // Check notification was created
        $this->assertDatabaseHas('packing_notifications', [
            'user_id' => $this->user->id,
            'type' => 'alert',
            'level' => 'warning',
            'title' => 'Target Diperbarui',
        ]);
    }

    public function test_tracks_who_updated_the_target()
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');
        $this->actingAs($supervisor);

        // Create initial task
        $firstResult = $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            50
        );

        // Update task
        $updateResult = $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            75,
            true
        );

        // Verify assigned_by is tracked
        $task = DailyPackingTask::find($updateResult['task_id']);
        $this->assertEquals($supervisor->id, $task->assigned_by);
    }

    public function test_includes_carry_over_when_calculating_total_target()
    {
        // Create yesterday's incomplete task
        $yesterdayTask = DailyPackingTask::create([
            'user_id' => $this->user->id,
            'tanggal_tugas' => today()->subDay(),
            'total_target' => 100,
            'total_selesai' => 70,
            'status' => DailyPackingTask::STATUS_IN_PROGRESS,
        ]);

        // Expire yesterday's task (normally done by cron)
        $yesterdayTask->update(['status' => DailyPackingTask::STATUS_EXPIRED]);

        // Assign today's task
        $result = $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            50
        );

        // Total target should be 50 (new) + 30 (carry over from yesterday)
        $this->assertEquals(80, $result['total_target']);
        $this->assertEquals(30, $result['carry_over']);
        $this->assertEquals(50, $result['custom_target']);
    }

    public function test_can_update_task_and_preserve_carry_over()
    {
        // Create yesterday's incomplete task
        DailyPackingTask::create([
            'user_id' => $this->user->id,
            'tanggal_tugas' => today()->subDay(),
            'total_target' => 100,
            'total_selesai' => 70,
            'status' => DailyPackingTask::STATUS_EXPIRED,
        ]);

        // Assign today's task (with carry over)
        $firstResult = $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            50
        );

        $this->assertEquals(80, $firstResult['total_target']); // 50 + 30 carry over

        // Update task
        $updateResult = $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            75,
            true
        );

        // New total should be 75 + 30 carry over = 105
        $this->assertEquals(105, $updateResult['new_target']);
        $this->assertEquals(75, $updateResult['new_base_target']);
        $this->assertEquals(30, $updateResult['carry_over']);
    }

    public function test_returns_correct_status_based_on_operation()
    {
        // New assignment
        $newResult = $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            50
        );
        $this->assertEquals('manual_assigned', $newResult['status']);

        // Update
        $updateResult = $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            75,
            true
        );
        $this->assertEquals('target_updated', $updateResult['status']);
    }
}
