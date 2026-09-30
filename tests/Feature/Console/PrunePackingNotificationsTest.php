<?php

namespace Tests\Feature\Console;

use App\Models\DailyPackingTask;
use App\Models\PackingNotification;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrunePackingNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function taskOn(string $date): DailyPackingTask
    {
        return DailyPackingTask::create([
            'user_id' => User::factory()->create()->id,
            'tanggal_tugas' => $date,
            'total_target' => 80,
            'total_selesai' => 0,
            'status' => DailyPackingTask::STATUS_ASSIGNED,
            'assignment_method' => 'target_only',
        ]);
    }

    private function notification(DailyPackingTask $task, array $attributes = []): PackingNotification
    {
        return PackingNotification::create(array_merge([
            'user_id' => $task->user_id,
            'daily_packing_task_id' => $task->id,
            'type' => PackingNotification::TYPE_REMINDER,
            'level' => PackingNotification::LEVEL_INFO,
            'title' => 'Progress Check',
            'message' => 'Pesan uji',
            'is_read' => false,
        ], $attributes));
    }

    public function test_removes_notifications_for_past_days_even_when_unread(): void
    {
        // Every checkpoint produces one reminder per user per day, so an unread
        // pile never clears on its own — a 3pm reminder for yesterday cannot be
        // acted on today.
        $old = $this->taskOn(today()->subDays(3)->toDateString());
        $this->notification($old, ['is_read' => false]);

        $this->artisan('packing:prune-notifications')->assertSuccessful();

        expect(PackingNotification::count())->toBe(0);
    }

    public function test_keeps_notifications_for_today(): void
    {
        $today = $this->taskOn(today()->toDateString());
        $this->notification($today);

        $this->artisan('packing:prune-notifications')->assertSuccessful();

        expect(PackingNotification::count())->toBe(1);
    }

    public function test_keeps_notifications_for_tasks_belonging_to_today(): void
    {
        $today = $this->taskOn(today()->toDateString());
        $this->notification($today, ['is_read' => true]);

        $yesterday = $this->taskOn(today()->subDay()->toDateString());
        $this->notification($yesterday, ['is_read' => true]);

        $this->artisan('packing:prune-notifications')->assertSuccessful();

        $remaining = PackingNotification::pluck('daily_packing_task_id')->all();

        expect($remaining)->toBe([$today->id]);
    }

    public function test_removes_read_orphans_past_the_cutoff(): void
    {
        // Orphan: the task it belonged to no longer exists.
        $orphan = $this->notification(
            $this->taskOn(today()->toDateString()),
            ['is_read' => true]
        );
        $orphan->forceFill([
            'created_at' => now()->subDays(90),
            'daily_packing_task_id' => null,
        ])->save();

        $this->artisan('packing:prune-notifications --days=30')->assertSuccessful();

        expect(PackingNotification::count())->toBe(0);
    }

    public function test_keeps_unread_orphans_so_nobody_misses_information(): void
    {
        $orphan = $this->notification(
            $this->taskOn(today()->toDateString()),
            ['is_read' => false]
        );
        $orphan->forceFill([
            'created_at' => now()->subDays(90),
            'daily_packing_task_id' => null,
        ])->save();

        $this->artisan('packing:prune-notifications --days=30')->assertSuccessful();

        expect(PackingNotification::count())->toBe(1);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $old = $this->taskOn(today()->subDays(3)->toDateString());
        $this->notification($old);

        $this->artisan('packing:prune-notifications --dry-run')->assertSuccessful();

        expect(PackingNotification::count())->toBe(1);
    }

    public function test_prune_is_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'packing:prune-notifications'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('45 23 * * *');
    }
}
