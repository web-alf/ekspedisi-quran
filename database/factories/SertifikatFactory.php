<?php

namespace Database\Factories;

use App\Models\CertificateTemplate;
use App\Models\Pengiriman;
use App\Models\Sertifikat;
use App\Models\User;
use App\Models\WakafBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class SertifikatFactory extends Factory
{
    protected $model = Sertifikat::class;

    public function definition(): array
    {
        return [
            'wakaf_batch_id' => WakafBatch::factory(),
            'pengiriman_id' => null, // Legacy field
            'template_used' => 'template1',
            'template_id' => function () {
                return CertificateTemplate::first()?->id;
            },
            'file_path' => 'certificates/'.$this->faker->uuid().'.pdf',
            'generated_by' => User::factory(),
            'generated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'is_sent' => false,
            'sent_at' => null,
        ];
    }

    /**
     * Sertifikat yang sudah dikirim
     */
    public function sent(): static
    {
        return $this->state([
            'is_sent' => true,
            'sent_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Sertifikat belum dikirim
     */
    public function notSent(): static
    {
        return $this->state([
            'is_sent' => false,
            'sent_at' => null,
        ]);
    }

    /**
     * Sertifikat dengan file yang ada
     */
    public function withFile(): static
    {
        return $this->afterCreating(function (Sertifikat $sertifikat) {
            // Create fake PDF content
            \Storage::put($sertifikat->file_path, '%PDF-1.4 fake content');
        });
    }

    /**
     * Sertifikat legacy (dengan pengiriman_id)
     */
    public function legacy(): static
    {
        return $this->state([
            'pengiriman_id' => Pengiriman::factory(),
            'wakaf_batch_id' => null,
        ]);
    }
}
