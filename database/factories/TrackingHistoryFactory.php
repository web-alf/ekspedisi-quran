<?php

namespace Database\Factories;

use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\TrackingHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackingHistory>
 */
class TrackingHistoryFactory extends Factory
{
    protected $model = TrackingHistory::class;

    public function definition(): array
    {
        return [
            'pengiriman_id' => Pengiriman::factory(),
            'status_id' => StatusPengiriman::factory(),
            'user_id' => User::factory(),
            'tanggal_update' => now(),
            'lokasi' => $this->faker->city(),
            'keterangan' => $this->faker->sentence(),
            'foto_dokumentasi' => null,
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
        ];
    }

    public function withPhoto(): self
    {
        return $this->state(fn () => [
            'foto_dokumentasi' => ['tracking/'.$this->faker->uuid().'.jpg'],
        ]);
    }
}