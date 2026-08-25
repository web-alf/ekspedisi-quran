<?php

namespace Database\Factories;

use App\Models\DailyPackingTask;
use App\Models\PackingBox;
use App\Models\SharedBoxAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SharedBoxAssignment>
 */
class SharedBoxAssignmentFactory extends Factory
{
    protected $model = SharedBoxAssignment::class;

    public function definition(): array
    {
        return [
            'packing_box_id' => PackingBox::factory(),
            'daily_packing_task_id' => DailyPackingTask::factory(),
            'user_id' => User::factory(),
            'allocated_items' => $this->faker->numberBetween(5, 25),
            'completed_items' => 0,
            'started_at' => null,
            'completed_at' => null,
            'progress_metadata' => [],
        ];
    }

    public function started(): self
    {
        return $this->state(fn () => ['started_at' => now()]);
    }

    public function completed(): self
    {
        return $this->state(fn () => [
            'completed_items' => $this->faker->numberBetween(5, 25),
            'completed_at' => now(),
        ]);
    }
}