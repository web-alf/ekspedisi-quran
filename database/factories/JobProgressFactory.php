<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JobProgress>
 */
class JobProgressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'job_id' => fake()->uuid(),
            'job_type' => fake()->randomElement(['certificate_generation', 'bulk_processing', 'data_export']),
            'title' => fake()->sentence(3),
            'status' => fake()->randomElement(['pending', 'processing', 'completed', 'failed']),
            'progress_percentage' => fake()->numberBetween(0, 100),
            'total_items' => fake()->numberBetween(10, 1000),
            'processed_items' => function (array $attributes) {
                return fake()->numberBetween(0, $attributes['total_items']);
            },
            'failed_items' => fake()->numberBetween(0, 10),
            'started_at' => fake()->dateTimeBetween('-1 hour', 'now'),
            'completed_at' => null,
            'metadata' => [
                'source' => fake()->randomElement(['admin', 'warehouse', 'system']),
                'batch_id' => fake()->uuid(),
            ],
        ];
    }

    /**
     * Indicate that the job is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'progress_percentage' => fake()->numberBetween(1, 99),
            'started_at' => fake()->dateTimeBetween('-30 minutes', 'now'),
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the job is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'progress_percentage' => 100,
            'completed_at' => fake()->dateTimeBetween('-10 minutes', 'now'),
        ]);
    }

    /**
     * Indicate that the job has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'progress_percentage' => fake()->numberBetween(0, 99),
            'failed_items' => fake()->numberBetween(1, 50),
            'completed_at' => fake()->dateTimeBetween('-5 minutes', 'now'),
        ]);
    }
}
