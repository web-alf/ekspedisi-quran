<?php

namespace Tests\Feature\Console;

use App\Models\DailyPackingTask;
use App\Models\PackingNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PackingProgressCheckpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function scheduleEvent(): object
    {
        return collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'packing:check-progress'));
    }

    private function makeTaskForToday(): DailyPackingTask
    {
        return DailyPackingTask::create([
            'user_id' => User::factory()->create()->id,
            'tanggal_tugas' => today(),
            'total_target' => 80,
            'total_selesai' => 0,
            'status' => DailyPackingTask::STATUS_ASSIGNED,
            'assignment_method' => 'target_only',
        ]);
    }

    /**
     * `ScheduleRunCommand` runs an event when it is due AND its filters pass.
     * `isDue()` alone does not evaluate `between()` — that is a filter, and its
     * closure captures the clock at schedule-registration time. Every cron run
     * boots a fresh process, so to model that we re-evaluate a freshly built
     * event under the simulated clock.
     *
     * The window is read from routes/console.php so that reverting the fix
     * (back to '17:00') fails this test instead of silently passing.
     */
    private function windowFromSource(): array
    {
        $source = file_get_contents(base_path('routes/console.php'));

        preg_match(
            "/packing:check-progress'\)->hourly\(\)->between\('([^']+)',\s*'([^']+)'\)/",
            $source,
            $matches
        );

        expect($matches)->toHaveCount(3, 'Pola jadwal packing:check-progress tidak ditemukan di routes/console.php');

        return [$matches[1], $matches[2]];
    }

    private function runsAt(string $time): bool
    {
        Carbon::setTestNow(Carbon::parse(today()->format('Y-m-d').' '.$time));

        [$start, $end] = $this->windowFromSource();
        $event = (new Event(new CacheEventMutex(app('cache')), 'packing:check-progress'))
            ->hourly()
            ->between($start, $end);

        return $event->isDue(app()) && $event->filtersPass(app());
    }

    public function test_progress_check_is_due_at_every_checkpoint_hour(): void
    {
        // Cron fires schedule:run a fraction of a second AFTER the minute mark.
        // A 17:00 boundary excluded the 17:00 run, so the critical 5pm
        // checkpoint was never sent on production.
        foreach (['08:00:00.500', '10:00:00.500', '12:00:00.500', '15:00:00.500', '17:00:00.500'] as $time) {
            expect($this->runsAt($time))->toBeTrue("check-progress tidak jalan pada {$time}");
        }
    }

    public function test_progress_check_does_not_run_outside_working_hours(): void
    {
        foreach (['07:59:00.500', '18:00:00.500', '23:00:00.500'] as $time) {
            expect($this->runsAt($time))->toBeFalse("check-progress jalan di luar jam kerja: {$time}");
        }
    }

    public function test_progress_check_schedule_is_registered_as_hourly(): void
    {
        $event = $this->scheduleEvent();

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('0 * * * *');
    }

    public function test_critical_checkpoint_is_sent_at_5pm(): void
    {
        $task = $this->makeTaskForToday();

        // 17:00 WIB, with the sub-second delay cron actually has.
        Carbon::setTestNow(Carbon::parse(today()->format('Y-m-d').' 17:00:00.500'));

        Artisan::call('packing:check-progress');

        $this->assertDatabaseHas('packing_notifications', [
            'daily_packing_task_id' => $task->id,
            'type' => PackingNotification::TYPE_REMINDER,
            'level' => PackingNotification::LEVEL_CRITICAL,
            'title' => 'Progress Check',
        ]);

        $notification = PackingNotification::where('daily_packing_task_id', $task->id)->first();

        expect($notification->meta_data['checkpoint'])->toBe('5pm')
            ->and($notification->meta_data['expected_progress'])->toBe(80);
    }

    public function test_each_checkpoint_notifies_only_once_per_day(): void
    {
        $task = $this->makeTaskForToday();

        foreach (['10:00', '12:00', '15:00', '17:00'] as $time) {
            Carbon::setTestNow(Carbon::parse(today()->format('Y-m-d').' '.$time.':00.500'));
            Artisan::call('packing:check-progress');

            // Running the same hour twice (the scheduler fires every minute)
            // must not produce a second notification for that checkpoint.
            Artisan::call('packing:check-progress');
        }

        expect(PackingNotification::where('daily_packing_task_id', $task->id)->count())->toBe(4);

        $checkpoints = PackingNotification::where('daily_packing_task_id', $task->id)
            ->get()
            ->pluck('meta_data')
            ->pluck('checkpoint')
            ->sort()
            ->values()
            ->all();

        expect($checkpoints)->toBe(['10am', '12pm', '3pm', '5pm']);
    }

    public function test_no_notification_when_progress_meets_the_checkpoint(): void
    {
        DailyPackingTask::create([
            'user_id' => User::factory()->create()->id,
            'tanggal_tugas' => today(),
            'total_target' => 100,
            'total_selesai' => 100,
            'status' => DailyPackingTask::STATUS_COMPLETED,
            'assignment_method' => 'target_only',
        ]);

        Carbon::setTestNow(Carbon::parse(today()->format('Y-m-d').' 17:00:00.500'));
        Artisan::call('packing:check-progress');

        expect(PackingNotification::count())->toBe(0);
    }

    public function test_checkpoint_reminders_do_not_pile_up_across_working_days(): void
    {
        $task = $this->makeTaskForToday();

        // A full working day produces exactly four reminders, not one per minute.
        Carbon::setTestNow(Carbon::parse(today()->format('Y-m-d').' 09:00:00.000'));
        for ($minute = 0; $minute < 1; $minute++) {
            Artisan::call('packing:check-progress');
        }

        foreach (['10:00', '12:00', '15:00', '17:00'] as $time) {
            Carbon::setTestNow(Carbon::parse(today()->format('Y-m-d').' '.$time.':00.500'));
            Artisan::call('packing:check-progress');
        }

        expect(PackingNotification::where('daily_packing_task_id', $task->id)->count())->toBe(4);
    }
}
