<?php

namespace Database\Factories;

use App\Models\Donatur;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DonaturFactory extends Factory
{
    protected $model = Donatur::class;

    public function definition(): array
    {
        return [
            'kode_donatur' => 'DN-'.$this->faker->unique()->numerify('####'),
            'nama_donatur' => $this->faker->name(),
            'no_hp' => '+62'.$this->faker->numberBetween(812, 899).$this->faker->numerify('########'),
            'email_donatur' => $this->faker->unique()->safeEmail(),
            'alamat_donatur' => $this->faker->address(),
            'jenis_wakaf_dipilih' => ['A5', 'A6'],
            'total_a5_count' => $this->faker->numberBetween(1, 10),
            'total_a6_count' => $this->faker->numberBetween(1, 10),
            'total_iqra_count' => $this->faker->numberBetween(0, 5),
            'donation_count' => 1,
            'donation_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'doa_untuk_semua' => $this->faker->optional()->sentence(),
            'prayer_mode' => $this->faker->randomElement(['semua_donatur', 'customize_individual', 'mixed']),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Donatur dengan wakaf A5 saja
     */
    public function a5Only(): static
    {
        return $this->state([
            'jenis_wakaf_dipilih' => ['A5'],
            'total_a5_count' => $this->faker->numberBetween(1, 5),
            'total_a6_count' => 0,
            'total_iqra_count' => 0,
        ]);
    }

    /**
     * Donatur dengan wakaf A6 saja
     */
    public function a6Only(): static
    {
        return $this->state([
            'jenis_wakaf_dipilih' => ['A6'],
            'total_a5_count' => 0,
            'total_a6_count' => $this->faker->numberBetween(1, 5),
            'total_iqra_count' => 0,
        ]);
    }

    /**
     * Donatur dengan semua jenis wakaf
     */
    public function allTypes(): static
    {
        return $this->state([
            'jenis_wakaf_dipilih' => ['A5', 'A6', 'IQRA'],
            'total_a5_count' => $this->faker->numberBetween(1, 3),
            'total_a6_count' => $this->faker->numberBetween(1, 3),
            'total_iqra_count' => $this->faker->numberBetween(1, 3),
        ]);
    }

    /**
     * Donatur with semua_donatur prayer mode
     */
    public function semuaDonatur(): static
    {
        return $this->state([
            'prayer_mode' => 'semua_donatur',
            'doa_untuk_semua' => $this->faker->sentence(),
        ]);
    }

    /**
     * Donatur with customize_individual prayer mode
     */
    public function customizeIndividual(): static
    {
        return $this->state([
            'prayer_mode' => 'customize_individual',
            'doa_untuk_semua' => null,
        ]);
    }

    /**
     * Donatur with mixed prayer mode
     */
    public function mixed(): static
    {
        return $this->state([
            'prayer_mode' => 'mixed',
            'doa_untuk_semua' => $this->faker->optional()->sentence(),
        ]);
    }
}
