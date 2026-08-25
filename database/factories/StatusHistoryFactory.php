<?php

namespace Database\Factories;

use App\Models\Pengiriman;
use App\Models\StatusHistory;
use App\Models\StatusPengiriman;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusHistory>
 */
class StatusHistoryFactory extends Factory
{
    protected $model = StatusHistory::class;

    public function definition(): array
    {
        return [
            'pengiriman_id' => Pengiriman::factory(),
            'status_from' => StatusPengiriman::factory(),
            'status_to' => StatusPengiriman::factory(),
            'catatan' => $this->faker->sentence(),
            'lokasi' => $this->faker->city(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'dokumentasi' => null,
            'created_by' => User::factory(),
        ];
    }

    public function withDocumentation(): self
    {
        return $this->state(fn () => [
            'dokumentasi' => ['photos/'.$this->faker->uuid().'.jpg'],
        ]);
    }
}