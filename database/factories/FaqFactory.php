<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    protected $model = Faq::class;

    public function definition(): array
    {
        return [
            'question' => $this->faker->sentence(8, true).'?',
            'answer' => $this->faker->paragraph(3),
            'category' => $this->faker->randomElement(['donasi', 'distribusi', 'umum']),
            'sort_order' => $this->faker->numberBetween(1, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}