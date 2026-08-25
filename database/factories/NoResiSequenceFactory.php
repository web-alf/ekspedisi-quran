<?php

namespace Database\Factories;

use App\Models\NoResiSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NoResiSequence>
 */
class NoResiSequenceFactory extends Factory
{
    protected $model = NoResiSequence::class;

    public function definition(): array
    {
        $year = $this->faker->numberBetween(2024, 2026);

        return [
            'year' => $year,
            'last_number' => $this->faker->numberBetween(0, 99999),
        ];
    }
}