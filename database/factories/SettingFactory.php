<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(3),
            'label' => $this->faker->words(3, true),
            'value' => $this->faker->sentence(),
            'type' => 'string',
            'description' => $this->faker->sentence(),
            'options' => null,
            'group' => $this->faker->randomElement(['general', 'landing', 'contact', 'social', 'gallery', 'faq']),
            'sort_order' => 0,
            'is_public' => true,
            'is_active' => true,
        ];
    }

    public function private(): self
    {
        return $this->state(fn () => ['is_public' => false]);
    }
}