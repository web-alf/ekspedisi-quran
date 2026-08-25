<?php

namespace Database\Factories;

use App\Models\DailyPackingTask;
use App\Models\PackingNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackingNotification>
 */
class PackingNotificationFactory extends Factory
{
    protected $model = PackingNotification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'daily_packing_task_id' => DailyPackingTask::factory(),
            'type' => $this->faker->randomElement([
                PackingNotification::TYPE_REMINDER,
                PackingNotification::TYPE_WARNING,
                PackingNotification::TYPE_ALERT,
                PackingNotification::TYPE_COMPLETION,
            ]),
            'level' => $this->faker->randomElement([
                PackingNotification::LEVEL_INFO,
                PackingNotification::LEVEL_WARNING,
            ]),
            'title' => $this->faker->sentence(4),
            'message' => $this->faker->paragraph(),
            'is_read' => false,
            'read_at' => null,
            'meta_data' => [],
        ];
    }

    public function read(): self
    {
        return $this->state(fn () => ['is_read' => true, 'read_at' => now()]);
    }
}