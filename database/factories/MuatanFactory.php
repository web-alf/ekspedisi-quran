<?php

namespace Database\Factories;

use App\Models\Muatan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Muatan>
 */
class MuatanFactory extends Factory
{
    protected $model = Muatan::class;

    public function definition(): array
    {
        return [
            // kurir_id sengaja tidak memakai User::factory() karena muatan tanpa
            // kurir tetap sah (kolomnya nullable); tes yang butuh kurir
            // menyebutnya sendiri lewat ->forKurir().
            'kurir_id' => User::factory(),
            'created_by' => User::factory(),
            'tanggal_muatan' => now()->toDateString(),
            'nama_muatan' => $this->faker->optional()->words(2, true),
            'catatan' => $this->faker->optional()->sentence(),
            'total_resi' => 0,
            'total_mushaf' => 0,
        ];
    }

    public function tanggal(string $tanggal): static
    {
        return $this->state(fn () => ['tanggal_muatan' => $tanggal]);
    }

    public function untukKurir(User $kurir): static
    {
        return $this->state(fn () => ['kurir_id' => $kurir->id]);
    }

    public function tanpaKurir(): static
    {
        return $this->state(fn () => ['kurir_id' => null]);
    }
}
