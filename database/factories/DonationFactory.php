<?php

namespace Database\Factories;

use App\Models\Donation;
use App\Models\Donatur;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Donation>
 */
class DonationFactory extends Factory
{
    protected $model = Donation::class;

    public function definition(): array
    {
        return [
            'donatur_id' => Donatur::factory(),
            'jenis_donasi' => $this->faker->randomElement(['uang', 'mushaf']),
            'jumlah_donasi' => $this->faker->randomFloat(2, 100000, 10000000),
            'tanggal_donasi' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'metode_pembayaran' => $this->faker->randomElement(['transfer', 'cash', 'qris']),
            'status_donasi' => 'pending',
            'keterangan' => $this->faker->sentence(),
            'bukti_transfer' => null,
            'confirmed_at' => null,
            'confirmed_by' => null,
        ];
    }

    public function confirmed(): self
    {
        return $this->state(fn () => [
            'status_donasi' => 'confirmed',
            'confirmed_at' => now(),
            'confirmed_by' => User::factory(),
        ]);
    }
}