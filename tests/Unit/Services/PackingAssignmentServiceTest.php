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

    public function test_caps_carry_over_at_the_configured_percentage(): void
    {
        // Yesterday left 30 undone. Base target 50, cap 20% = 10, so only 10 carries.
        DailyPackingTask::create([
            'user_id' => $this->user->id,
            'tanggal_tugas' => today()->subDay(),
            'total_target' => 100,
            'total_selesai' => 70,
            'status' => DailyPackingTask::STATUS_EXPIRED,
            'assignment_method' => 'target_only',
        ]);

        $result = $this->service->assignDailyTaskToUserWithTarget($this->user, today(), 50);

        expect($result['carry_over'])->toBe(10)
            ->and($result['total_target'])->toBe(60);
    }

    public function test_carry_over_grows_with_the_base_target(): void
    {
        DailyPackingTask::create([
            'user_id' => $this->user->id,
            'tanggal_tugas' => today()->subDay(),
            'total_target' => 1000,
            'total_selesai' => 0,
            'status' => DailyPackingTask::STATUS_EXPIRED,
            'assignment_method' => 'target_only',
        ]);

        // 20% of 100 = 20, 20% of 500 = 100 -> target 180 then 600.
        $small = $this->service->assignDailyTaskToUserWithTarget($this->user, today(), 100);

        expect($small['carry_over'])->toBe(20)
            ->and($small['total_target'])->toBe(120);
    }

    public function test_carry_over_is_unbounded_when_the_limit_is_100_or_more(): void
    {
        config(['packing.task_expiration.max_carryover_percent' => 100]);

        DailyPackingTask::create([
            'user_id' => $this->user->id,
            'tanggal_tugas' => today()->subDay(),
            'total_target' => 100,
            'total_selesai' => 10,
            'status' => DailyPackingTask::STATUS_EXPIRED,
            'assignment_method' => 'target_only',
        ]);

        $result = $this->service->assignDailyTaskToUserWithTarget($this->user, today(), 50);

        expect($result['carry_over'])->toBe(90)
            ->and($result['total_target'])->toBe(140);
    }

    public function test_carry_over_is_zero_when_disabled(): void
    {
        config(['packing.task_expiration.allow_carryover' => false]);

        DailyPackingTask::create([
            'user_id' => $this->user->id,
            'tanggal_tugas' => today()->subDay(),
            'total_target' => 100,
            'total_selesai' => 70,
            'status' => DailyPackingTask::STATUS_EXPIRED,
            'assignment_method' => 'target_only',
        ]);

        $result = $this->service->assignDailyTaskToUserWithTarget($this->user, today(), 50);

        expect($result['carry_over'])->toBe(0)
            ->and($result['total_target'])->toBe(50);
    }

    public function test_carry_over_does_not_compound_across_days(): void
    {
        // A team that packs nothing for three days must not climb 80 -> 160 -> 240.
        // Each day: base 80 plus at most 20% (16) = 96, never more.
        DailyPackingTask::create([
            'user_id' => $this->user->id,
            'tanggal_tugas' => today()->subDays(3),
            'total_target' => 80,
            'total_selesai' => 0,
            'status' => DailyPackingTask::STATUS_EXPIRED,
            'assignment_method' => 'target_only',
        ]);

        $day1 = $this->service->assignDailyTaskToUserWithTarget($this->user, today()->subDays(2), 80, true);
        $day2 = $this->service->assignDailyTaskToUserWithTarget($this->user, today()->subDay(), 80, true);
        $day3 = $this->service->assignDailyTaskToUserWithTarget($this->user, today(), 80, true);

        expect($day1['carry_over'])->toBe(16)
            ->and($day2['carry_over'])->toBe(16)
            ->and($day3['carry_over'])->toBe(16);

        // Every task stays at base + cap instead of compounding.
        foreach ([$day1, $day2, $day3] as $day) {
            expect($day['total_target'])->toBe(96);
        }
    }

    public function test_includes_carry_over_when_calculating_total_target(): void
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

        // Assign today's task. 30 was left undone, but the cap for a base target
        // of 50 is 20% = 10, so the carry over is bounded.
        $result = $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            50
        );

        $this->assertEquals(60, $result['total_target']);
        $this->assertEquals(10, $result['carry_over']);
        $this->assertEquals(50, $result['custom_target']);
    }

    public function test_can_update_task_and_preserve_carry_over(): void
    {
        // Create yesterday's incomplete task
        DailyPackingTask::create([
            'user_id' => $this->user->id,
            'tanggal_tugas' => today()->subDay(),
            'total_target' => 100,
            'total_selesai' => 70,
            'status' => DailyPackingTask::STATUS_EXPIRED,
            'assignment_method' => 'target_only',
        ]);

        // Assign today's task (carry over capped to 10 for a base target of 50)
        $firstResult = $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            50
        );

        $this->assertEquals(60, $firstResult['total_target']); // 50 + capped 10

        // Update task. The stored carry over is reused, not recalculated,
        // so 75 + 10 = 85.
        $updateResult = $this->service->assignDailyTaskToUserWithTarget(
            $this->user,
            today(),
            75,
            true
        );

        $this->assertEquals(85, $updateResult['new_target']);
        $this->assertEquals(75, $updateResult['new_base_target']);
        $this->assertEquals(10, $updateResult['carry_over']);
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
