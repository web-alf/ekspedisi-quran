<?php

namespace Database\Factories;

use App\Models\WhatsAppSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppSetting>
 */
class WhatsAppSettingFactory extends Factory
{
    protected $model = WhatsAppSetting::class;

    public function definition(): array
    {
        return [
            'provider' => 'starsender',
            'api_key' => 'kunci-uji-'.$this->faker->numerify('######'),
            'base_url' => 'https://api.starsender.online',
            'sender_number' => '6281234567890',
            'is_active' => true,
            'delay_seconds' => 0,
            'max_per_minute' => 60,
            'quiet_hours_start' => null,
            'quiet_hours_end' => null,
        ];
    }

    /**
     * Belum bisa dipakai kirim — untuk menguji bahwa alur tidak rusak.
     */
    public function belumSiap(): static
    {
        return $this->state([
            'is_active' => false,
            'api_key' => null,
        ]);
    }
}
