<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\LandingSectionRegistry;
use Illuminate\Database\Seeder;

class LandingSectionOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::updateOrCreate(
            ['key' => 'landing_section_order'],
            [
                'label' => 'Urutan Section Landing Page',
                'value' => json_encode(LandingSectionRegistry::getDefaultOrder()),
                'type' => 'json',
                'description' => 'Mengatur urutan dan visibilitas section pada halaman utama',
                'group' => 'landing',
                'sort_order' => 0,
                'is_public' => true,
                'is_active' => true,
            ]
        );

        if ($this->command) {
            $this->command->info('✅ Default landing section order seeded successfully!');
        }
    }
}
