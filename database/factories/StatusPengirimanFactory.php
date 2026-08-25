<?php

namespace Database\Factories;

use App\Models\StatusPengiriman;
use Illuminate\Database\Eloquent\Factories\Factory;

class StatusPengirimanFactory extends Factory
{
    protected $model = StatusPengiriman::class;

    public function definition(): array
    {
        return [
            'nama' => $this->faker->unique()->randomElement([
                'Pending', 'Processing', 'Shipped', 'Delivered', 'Completed',
            ]),
            'slug' => $this->faker->unique()->slug(),
            'deskripsi' => $this->faker->sentence(),
            'warna' => $this->faker->randomElement(['blue', 'green', 'yellow', 'red', 'purple']),
            'urutan' => $this->faker->numberBetween(1, 10),
            'is_active' => true,
            'is_final' => false,
            'icon' => 'fas fa-'.$this->faker->randomElement([
                'clock', 'cog', 'shipping-fast', 'check-circle', 'trophy',
            ]),
        ];
    }

    /**
     * Status default
     */
    public function default(): static
    {
        return $this->state([
            'nama' => 'Pending',
            'slug' => 'pending',
            'urutan' => 1,
        ]);
    }
}
