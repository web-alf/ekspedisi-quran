<?php

namespace Database\Factories;

use App\Models\Muatan;
use App\Models\MuatanItem;
use App\Models\Pengiriman;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MuatanItem>
 */
class MuatanItemFactory extends Factory
{
    protected $model = MuatanItem::class;

    public function definition(): array
    {
        return [
            'muatan_id' => Muatan::factory(),
            'pengiriman_id' => Pengiriman::factory(),
            'urutan' => null,
            'dimuat_at' => now(),
            'dimuat_by' => User::factory(),
            'catatan' => null,
        ];
    }
}
