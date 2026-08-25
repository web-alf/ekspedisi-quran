<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserPerformance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPerformance>
 */
class UserPerformanceFactory extends Factory
{
    protected $model = UserPerformance::class;

    public function definition(): array
    {
        $target = $this->faker->numberBetween(100, 1000);
        $achieved = $this->faker->numberBetween(50, $target);

        return [
            'user_id' => User::factory(),
            'bulan' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m'),
            'total_target' => $target,
            'total_achieved' => $achieved,
            'achievement_rate' => round(($achieved / $target) * 100, 2),
            'total_hari_kerja' => $this->faker->numberBetween(15, 25),
            'total_hari_complete' => $this->faker->numberBetween(10, 25),
            'total_carry_over' => $this->faker->numberBetween(0, 10),
            'warning_count' => $this->faker->numberBetween(0, 5),
            'avg_completion_time' => $this->faker->numberBetween(1800, 14400),
        ];
    }
}