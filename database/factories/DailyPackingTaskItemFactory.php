<?php

namespace Database\Factories;

use App\Models\DailyPackingTask;
use App\Models\DailyPackingTaskItem;
use App\Models\Pengiriman;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyPackingTaskItem>
 */
class DailyPackingTaskItemFactory extends Factory
{
    protected $model = DailyPackingTaskItem::class;

    public function definition(): array
    {
        return [
            'daily_packing_task_id' => DailyPackingTask::factory(),
            'pengiriman_id' => Pengiriman::factory(),
            'is_packed' => false,
            'assigned_at' => now(),
            'packed_at' => null,
        ];
    }

    public function packed(): self
    {
        return $this->state(fn () => ['is_packed' => true, 'packed_at' => now()]);
    }
}