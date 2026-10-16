<?php

namespace Database\Factories;

use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppTemplate>
 */
class WhatsAppTemplateFactory extends Factory
{
    protected $model = WhatsAppTemplate::class;

    public function definition(): array
    {
        return [
            'name' => 'uji_'.$this->faker->unique()->word(),
            'category' => 'uji',
            'title' => 'Template Uji',
            'content' => 'Halo {nama}, ini pesan uji.',
            'variables' => ['nama'],
            'is_active' => true,
            'description' => null,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(['is_active' => false]);
    }
}
