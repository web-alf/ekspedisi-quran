<?php

namespace Database\Factories;

use App\Models\Gudang;
use App\Models\TargetPeriodeGudang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TargetPeriodeGudang>
 */
class TargetPeriodeGudangFactory extends Factory
{
    protected $model = TargetPeriodeGudang::class;

    /**
     * Target bulanan bulan berjalan untuk semua jenis.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$mulai, $selesai] = TargetPeriodeGudang::rentang(TargetPeriodeGudang::TIPE_BULANAN, today());

        return [
            // Gudang SMG/BTL dibuat oleh migrasi; Gudang tidak punya factory.
            'gudang_id' => fn () => Gudang::query()->orderBy('id')->value('id'),
            'tipe' => TargetPeriodeGudang::TIPE_BULANAN,
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_selesai' => $selesai->toDateString(),
            'jenis_quran_id' => null,
            'terjemahan' => null,
            'kunci_jenis' => TargetPeriodeGudang::KUNCI_SEMUA_JENIS,
            'target' => $this->faker->numberBetween(100, 2000),
            'catatan' => null,
        ];
    }

    public function mingguan(): static
    {
        [$mulai, $selesai] = TargetPeriodeGudang::rentang(TargetPeriodeGudang::TIPE_MINGGUAN, today());

        return $this->state([
            'tipe' => TargetPeriodeGudang::TIPE_MINGGUAN,
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_selesai' => $selesai->toDateString(),
        ]);
    }
}
