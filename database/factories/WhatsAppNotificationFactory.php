<?php

namespace Database\Factories;

use App\Models\WhatsAppNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppNotification>
 */
class WhatsAppNotificationFactory extends Factory
{
    protected $model = WhatsAppNotification::class;

    public function definition(): array
    {
        return [
            'event_key' => 'uji_peristiwa',
            'dedupe_key' => 'uji:'.$this->faker->unique()->numerify('########'),
            'recipient' => '6281234567890',
            'recipient_name' => $this->faker->name(),
            'body' => 'Pesan uji.',
            'status' => 'menunggu',
            'attempts' => 0,
        ];
    }

    public function terkirim(): static
    {
        return $this->state([
            'status' => 'terkirim',
            'attempts' => 1,
            'sent_at' => now(),
        ]);
    }

    public function gagal(): static
    {
        return $this->state([
            'status' => 'gagal',
            'attempts' => 1,
            'error_message' => 'Nomor tidak terdaftar WhatsApp.',
        ]);
    }
}
