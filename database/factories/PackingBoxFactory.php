<?php

namespace Database\Factories;

use App\Models\JenisQuran;
use App\Models\PackingBox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PackingBox>
 */
class PackingBoxFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jenisQuran = JenisQuran::inRandomOrder()->first() ?? JenisQuran::factory()->create();

        // Set capacity based on jenis
        $capacity = match ($jenisQuran->kode_jenis) {
            'A5' => 30,
            'A6' => 50,
            'IQRA' => 40,
            default => 30,
        };

        return [
            'kode_kerdus' => 'KB-'.date('Ymd').'-'.str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT).'-'.$jenisQuran->kode_jenis.'-'.str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT),
            'jenis_quran_id' => $jenisQuran->id,
            'kapasitas' => $capacity,
            'jumlah_terisi' => 0,
            'status' => PackingBox::STATUS_EMPTY,
            'is_shared_box' => false,
            'assignment_type' => 'individual',
        ];
    }

    /**
     * Indicate that the box is filling.
     */
    public function filling(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PackingBox::STATUS_FILLING,
            'jumlah_terisi' => rand(1, $attributes['kapasitas'] - 1),
        ]);
    }

    /**
     * Indicate that the box is full.
     */
    public function full(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PackingBox::STATUS_FULL,
            'jumlah_terisi' => $attributes['kapasitas'],
        ]);
    }

    /**
     * Indicate that the box is sealed.
     */
    public function sealed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PackingBox::STATUS_SEALED,
            'jumlah_terisi' => $attributes['kapasitas'],
            'sealed_at' => now(),
            'seal_code' => strtoupper(bin2hex(random_bytes(4))),
        ]);
    }

    /**
     * Indicate that this is a shared box.
     */
    public function shared(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_shared_box' => true,
            'assignment_type' => 'shared',
        ]);
    }
}
