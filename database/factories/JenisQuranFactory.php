<?php

namespace Database\Factories;

use App\Models\JenisQuran;
use Illuminate\Database\Eloquent\Factories\Factory;

class JenisQuranFactory extends Factory
{
    protected $model = JenisQuran::class;

    public function definition(): array
    {
        return [
            'kode_jenis' => strtoupper($this->faker->unique()->bothify('JN-##??')),
            'nama_jenis' => 'Jenis '.$this->faker->unique()->word(),
            'deskripsi' => $this->faker->sentence(),
            'harga' => $this->faker->numberBetween(25000, 75000),
            'is_active' => true,
        ];
    }

    /**
     * Jenis A5
     */
    public function a5(): static
    {
        return $this->state([
            'kode_jenis' => 'A5',
            'nama_jenis' => 'Al-Quran A5',
            'harga' => 50000,
        ]);
    }

    /**
     * Jenis A6
     */
    public function a6(): static
    {
        return $this->state([
            'kode_jenis' => 'A6',
            'nama_jenis' => 'Al-Quran A6',
            'harga' => 35000,
        ]);
    }

    /**
     * Jenis Iqra
     */
    public function iqra(): static
    {
        return $this->state([
            'kode_jenis' => 'IQRA',
            'nama_jenis' => 'Iqra',
            'harga' => 25000,
        ]);
    }
}
