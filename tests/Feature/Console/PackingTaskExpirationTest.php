<?php

namespace Tests\Feature\Console;

use App\Models\DailyPackingTask;
use App\Models\PackingNotification;
use App\Models\User;
use App\Services\PackingAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PackingTaskExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    public function test_expire_tasks_command_is_registered(): void
    {
        // The schedule in routes/console.php has always called this command, but
        // the command class did not exist, so the nightly run failed with
        // "Command packing:expire-tasks is not defined".
        $this->assertArrayHasKey('packing:expire-tasks', Artisan::all());
    }

    public function test_expires_unfinished_tasks_from_the_previous_day(): void
    {
        $user = User::factory()->create();

        $yesterday = DailyPackingTask::create([
            'user_id' => $user->id,
            'tanggal_tugas' => today()->subDay(),
            'total_target' => 100,
            'total_selesai' => 10,
            'status' => DailyPackingTask::STATUS_IN_PROGRESS,
            'assignment_method' => 'target_only',
        ]);

        $this->artisan('packing:expire-tasks')->assertSuccessful();

        $yesterday->refresh();

        expect($yesterday->status)->toBe(DailyPackingTask::STATUS_EXPIRED)
            ->and($yesterday->expired_at)->not->toBeNull();
    }

    public function test_warns_users_who_achieved_less_than_half_of_the_target(): void
    {
        $user = User::factory()->create();

        $yesterday = DailyPackingTask::create([
            'user_id' => $user->id,
            'tanggal_tugas' => today()->subDay(),
            'total_target' => 100,
            'total_selesai' => 10,
            'status' => DailyPackingTask::STATUS_ASSIGNED,
            'assignment_method' => 'target_only',
        ]);

        $this->artisan('packing:expire-tasks')->assertSuccessful();

        $this->assertDatabaseHas('packing_notifications', [
            'user_id' => $user->id,
            'daily_packing_task_id' => $yesterday->id,
            'type' => PackingNotification::TYPE_WARNING,
            'level' => PackingNotification::LEVEL_CRITICAL,
        ]);
    }

    public function test_does_not_warn_users_who_achieved_half_or_more(): void
    {
        $user = User::factory()->create();

        DailyPackingTask::create([
            'user_id' => $user->id,
            'tanggal_tugas' => today()->subDay(),
            'total_target' => 100,
            'total_selesai' => 60,
            'status' => DailyPackingTask::STATUS_IN_PROGRESS,
            'assignment_method' => 'target_only',
        ]);

        $this->artisan('packing:expire-tasks')->assertSuccessful();

        expect(PackingNotification::where('user_id', $user->id)->count())->toBe(0);
    }

    public function test_leaves_todays_tasks_untouched(): void
    {
        $user = User::factory()->create();

        $today = DailyPackingTask::create([
            'user_id' => $user->id,
            'tanggal_tugas' => today(),
            'total_target' => 100,
            'total_selesai' => 5,
            'status' => DailyPackingTask::STATUS_IN_PROGRESS,
            'assignment_method' => 'target_only',
        ]);

        $this->artisan('packing:expire-tasks')->assertSuccessful();

        // expireTask() only acts on tasks from before today, so today's work stays open.
        expect($today->refresh()->status)->toBe(DailyPackingTask::STATUS_IN_PROGRESS)
            ->and($today->expired_at)->toBeNull();
    }

    public function test_leaves_already_completed_tasks_untouched(): void
    {
        $user = User::factory()->create();

        $completed = DailyPackingTask::create([
            'user_id' => $user->id,
            'tanggal_tugas' => today()->subDay(),
            'total_target' => 100,
            'total_selesai' => 100,
            'status' => DailyPackingTask::STATUS_COMPLETED,
            'assignment_method' => 'target_only',
        ]);

        $this->artisan('packing:expire-tasks')->assertSuccessful();

        expect($completed->refresh()->status)->toBe(DailyPackingTask::STATUS_COMPLETED);
    }

    public function test_carries_the_unfinished_remainder_into_todays_target(): void
    {
        $user = User::factory()->create();

        DailyPackingTask::create([
            'user_id' => $user->id,
            'tanggal_tugas' => today()->subDay(),
            'total_target' => 100,
            'total_selesai' => 70,
            'status' => DailyPackingTask::STATUS_IN_PROGRESS,
            'assignment_method' => 'target_only',
        ]);

        // Expiration runs at 23:59, then the 06:00 assignment picks up the remainder.
        $this->artisan('packing:expire-tasks')->assertSuccessful();

        $assignment = app(PackingAssignmentService::class);
        $result = $assignment->assignDailyTaskToUserWithTarget($user, today(), 50);

        expect($result['carry_over'])->toBe(30)
            ->and($result['total_target'])->toBe(80);
    }
}
