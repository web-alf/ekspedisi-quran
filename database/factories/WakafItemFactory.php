<?php

namespace Database\Factories;

use App\Models\Donatur;
use App\Models\User;
use App\Models\WakafItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class WakafItemFactory extends Factory
{
    protected $model = WakafItem::class;

    /**
     * Nomor urut harus UNIK per donatur + jenis (dijaga kunci di database). Nomor acak
     * seperti sebelumnya menghasilkan tabrakan yang tidak mungkin terjadi pada data
     * nyata — satu donatur tidak pernah punya dua item A5 bernomor 1 — sehingga tes
     * gagal karena keadaan yang memang mustahil.
     */
    protected static int $urutan = 0;

    public function definition(): array
    {
        return [
            'donatur_id' => Donatur::factory(),
            'pengiriman_id' => null,
            'wakaf_type' => $this->faker->randomElement(['A5', 'A6', 'IQRA']),
            'sequence_in_type' => ++static::$urutan,
            'global_sequence' => static::$urutan,
            'wakif_name' => $this->faker->name(),
            'doa_request' => $this->faker->optional()->sentence(),
            'relationship_to_donatur' => $this->faker->randomElement(['Diri sendiri', 'Keluarga', 'Teman', 'Saudara']),
            'status' => $this->faker->randomElement(['pending', 'processed', 'shipped', 'delivered']),
            'catatan' => $this->faker->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => 'pending',
        ]);
    }

    public function processed(): static
    {
        return $this->state([
            'status' => 'processed',
        ]);
    }

    public function shipped(): static
    {
        return $this->state([
            'status' => 'shipped',
        ]);
    }

    public function delivered(): static
    {
        return $this->state([
            'status' => 'delivered',
        ]);
    }

    public function a5(): static
    {
        return $this->state([
            'wakaf_type' => 'A5',
        ]);
    }

    public function a6(): static
    {
        return $this->state([
            'wakaf_type' => 'A6',
        ]);
    }

    public function iqra(): static
    {
        return $this->state([
            'wakaf_type' => 'IQRA',
        ]);
    }
}
