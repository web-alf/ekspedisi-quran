<?php

namespace Database\Factories;

use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackingItem>
 */
class PackingItemFactory extends Factory
{
    protected $model = PackingItem::class;

    public function definition(): array
    {
        return [
            'packing_box_id' => PackingBox::factory(),
            'pengiriman_id' => Pengiriman::factory(),
            'packed_by' => User::factory(),
            'packed_at' => now(),
            'urutan_dalam_box' => $this->faker->numberBetween(1, 25),
            'scan_method' => $this->faker->randomElement(['qr_scan', 'manual', 'bulk']),
            'quantity' => 1,
        ];
    }
}