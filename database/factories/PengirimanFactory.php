<?php

namespace Database\Factories;

use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PengirimanFactory extends Factory
{
    protected $model = Pengiriman::class;

    public function definition(): array
    {
        return [
            'donatur_id' => Donatur::factory(),
            'jenis_quran_id' => function () {
                return JenisQuran::first()?->id ?? JenisQuran::factory()->create()->id;
            },
            'jumlah_quran' => $this->faker->numberBetween(1, 10),
            'tanggal_wakaf' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'status_id' => function () {
                return StatusPengiriman::where('nama', 'Pending')->first()?->id ??
                       StatusPengiriman::factory()->create(['nama' => 'Pending'])->id;
            },
            'alamat_tujuan' => $this->faker->address(),
            'nama_penerima' => $this->faker->name(),
            'nama_lembaga' => $this->faker->company(),
            'no_hp_penerima' => $this->faker->phoneNumber(),
            'catatan' => $this->faker->optional()->sentence(),
            'sertifikat_generated' => false,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Pengiriman dengan status pending
     */
    public function pending(): static
    {
        return $this->state([
            'status_id' => function () {
                return StatusPengiriman::where('nama', 'Pending')->first()?->id;
            },
        ]);
    }

    /**
     * Pengiriman dengan status processing
     */
    public function processing(): static
    {
        return $this->state([
            'status_id' => function () {
                return StatusPengiriman::where('nama', 'Processing')->first()?->id;
            },
        ]);
    }

    /**
     * Pengiriman dengan status delivered
     */
    public function delivered(): static
    {
        return $this->state([
            'status_id' => function () {
                return StatusPengiriman::where('nama', 'Delivered')->first()?->id;
            },
            'received_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'received_by' => $this->faker->name(),
            'receiver_contact' => $this->faker->phoneNumber(),
        ]);
    }

    /**
     * Pengiriman dengan alamat lengkap
     */
    public function withAddress(): static
    {
        return $this->state([
            'alamat_tujuan' => $this->faker->streetAddress().', '.
                              $this->faker->city().', '.
                              $this->faker->state().' '.
                              $this->faker->postcode(),
        ]);
    }

    /**
     * Pengiriman tanpa alamat (untuk testing validation)
     */
    public function withoutAddress(): static
    {
        return $this->state([
            'alamat_tujuan' => null,
            'nama_penerima' => null,
            'no_hp_penerima' => null,
        ]);
    }
}
