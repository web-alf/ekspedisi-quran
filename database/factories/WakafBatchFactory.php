<?php

namespace Database\Factories;

use App\Models\Donatur;
use App\Models\User;
use App\Models\WakafBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class WakafBatchFactory extends Factory
{
    protected $model = WakafBatch::class;

    public function definition(): array
    {
        return [
            'batch_code' => 'WB-'.$this->faker->unique()->numerify('####-####'),
            'donatur_id' => Donatur::factory(),
            'jenis_quran_id' => function () {
                return JenisQuran::first()?->id ?? JenisQuran::factory()->create()->id;
            },
            'total_quran' => $this->faker->numberBetween(1, 20),
            'tanggal_wakaf' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'status' => $this->faker->randomElement(['pending_distribution', 'in_progress', 'completed']),
            'catatan' => $this->faker->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Batch yang completed
     */
    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
        ]);
    }

    /**
     * Batch yang pending
     */
    public function pending(): static
    {
        return $this->state([
            'status' => 'pending_distribution',
        ]);
    }

    /**
     * Batch A5 saja
     */
    public function a5Only(): static
    {
        return $this->state([
            'jenis_quran_id' => function () {
                return JenisQuran::where('kode_jenis', 'A5')->first()?->id ??
                       JenisQuran::factory()->a5()->create()->id;
            },
        ]);
    }
}
