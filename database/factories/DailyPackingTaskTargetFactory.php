<?php

namespace Database\Factories;

use App\Models\DailyPackingTask;
use App\Models\DailyPackingTaskTarget;
use App\Models\JenisQuran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyPackingTaskTarget>
 */
class DailyPackingTaskTargetFactory extends Factory
{
    protected $model = DailyPackingTaskTarget::class;

    public function definition(): array
    {
        return [
            'daily_packing_task_id' => DailyPackingTask::factory(),
            'jenis_quran_id' => JenisQuran::factory(),
            'target_boxes' => $this->faker->numberBetween(1, 5),
            'target_quantity' => $this->faker->numberBetween(10, 100),
            'completed_quantity' => 0,
            'box_capacity' => 25,
            'is_shared_box' => false,
            'shared_box_code' => null,
            'assignment_metadata' => [],
        ];
    }
}