<?php

namespace Database\Factories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(3),
            'value' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'type' => 'string',
            'is_public' => false,
        ];
    }

    public function public(): self
    {
        return $this->state(fn () => ['is_public' => true]);
    }
}