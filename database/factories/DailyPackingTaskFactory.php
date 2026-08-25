<?php

namespace Database\Factories;

use App\Models\DailyPackingTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyPackingTask>
 */
class DailyPackingTaskFactory extends Factory
{
    protected $model = DailyPackingTask::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tanggal_tugas' => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'total_target' => $this->faker->numberBetween(10, 100),
            'total_selesai' => 0,
            'sisa_kemarin' => 0,
            'status' => DailyPackingTask::STATUS_ASSIGNED,
            'assignment_method' => 'auto',
            'box_breakdown' => [],
            'has_shared_boxes' => false,
            'total_boxes_assigned' => 0,
            'total_boxes_completed' => 0,
            'assigned_at' => now(),
            'assigned_by' => User::factory(),
            'started_at' => null,
            'first_scan_at' => null,
            'completed_at' => null,
            'expired_at' => now()->addDay(),
            'notes' => null,
        ];
    }

    public function inProgress(): self
    {
        return $this->state(fn () => ['status' => DailyPackingTask::STATUS_IN_PROGRESS, 'started_at' => now()]);
    }

    public function completed(): self
    {
        return $this->state(fn () => ['status' => DailyPackingTask::STATUS_COMPLETED, 'completed_at' => now()]);
    }
}