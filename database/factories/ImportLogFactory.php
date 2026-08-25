<?php

namespace Database\Factories;

use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportLog>
 */
class ImportLogFactory extends Factory
{
    protected $model = ImportLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'filename' => $this->faker->slug(3).'.xlsx',
            'total_rows' => $total = $this->faker->numberBetween(10, 500),
            'success_rows' => $total,
            'failed_rows' => 0,
            'error_details' => null,
            'file_path' => 'imports/'.$this->faker->uuid().'.xlsx',
            'status' => 'completed',
        ];
    }

    public function failed(): self
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'success_rows' => 0,
            'failed_rows' => $this->faker->numberBetween(1, 50),
            'error_details' => ['rows' => [$this->faker->numberBetween(1, 100) => 'invalid email']],
        ]);
    }
}