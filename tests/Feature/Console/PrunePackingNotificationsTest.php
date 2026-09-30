<?php

namespace Tests\Feature\Console;

use App\Models\PackingNotification;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrunePackingNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function notification(array $attributes = []): PackingNotification
    {
        return PackingNotification::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'type' => PackingNotification::TYPE_REMINDER,
            'level' => PackingNotification::LEVEL_INFO,
            'title' => 'Progress Check',
            'message' => 'Pesan uji',
            'is_read' => false,
        ], $attributes));
    }

    private function backdate(PackingNotification $notification, int $days): void
    {
        $notification->forceFill(['created_at' => now()->subDays($days)])->save();
    }

    public function test_keeps_unread_notifications_regardless_of_age(): void
    {
        $old = $this->notification(['is_read' => false]);
        $this->backdate($old, 90);

        $this->artisan('packing:prune-notifications --days=30')->assertSuccessful();

        expect(PackingNotification::count())->toBe(1);
    }

    public function test_removes_read_notifications_older_than_the_cutoff(): void
    {
        $stale = $this->notification(['is_read' => true]);
        $this->backdate($stale, 90);

        $fresh = $this->notification(['is_read' => true]);
        $this->backdate($fresh, 2);

        $this->artisan('packing:prune-notifications --days=30')->assertSuccessful();

        expect(PackingNotification::pluck('id')->all())->toBe([$fresh->id]);
    }

    public function test_include_unread_clears_everything_past_the_cutoff(): void
    {
        $old = $this->notification(['is_read' => false]);
        $this->backdate($old, 90);

        $this->artisan('packing:prune-notifications --days=30 --include-unread')->assertSuccessful();

        expect(PackingNotification::count())->toBe(0);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $stale = $this->notification(['is_read' => true]);
        $this->backdate($stale, 90);

        $this->artisan('packing:prune-notifications --days=30 --dry-run')->assertSuccessful();

        expect(PackingNotification::count())->toBe(1);
    }

    public function test_prune_is_scheduled(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'packing:prune-notifications'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('45 23 * * *');
    }
}
