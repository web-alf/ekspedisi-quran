<?php

namespace Database\Factories;

use App\Models\CertificateTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateTemplateFactory extends Factory
{
    protected $model = CertificateTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true).' Template',
            'description' => $this->faker->sentence(),
            'template_file_path' => 'templates/'.$this->faker->uuid().'.jpg',
            'field_positions' => [
                'donatur_name' => ['x' => 100, 'y' => 200],
                'batch_code' => ['x' => 100, 'y' => 250],
                'date' => ['x' => 100, 'y' => 300],
                'signature' => ['x' => 400, 'y' => 500],
            ],
            'is_active' => true,
            'created_by' => 1,
        ];
    }

    /**
     * Template yang aktif
     */
    public function active(): static
    {
        return $this->state([
            'is_active' => true,
        ]);
    }

    /**
     * Template yang tidak aktif
     */
    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
        ]);
    }

    /**
     * Template default
     */
    public function default(): static
    {
        return $this->state([
            'name' => 'Default Certificate Template',
            'description' => 'Template sertifikat default untuk wakaf Al-Quran',
            'is_active' => true,
        ]);
    }
}
