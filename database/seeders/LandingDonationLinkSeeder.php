<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class LandingDonationLinkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $setting = [
            'key' => 'landing_donation_link',
            'label' => 'Link Mulai Berdonasi',
            'value' => 'https://alfatihah.com/campaign/ayo-bantu-maksimalkan-aktivitas-belajar-mengaji-dan-kirimkan-quran-ke-seluruh-penjuru-negeri',
            'type' => 'url',
            'description' => 'Link yang akan digunakan untuk tombol "Mulai Berdonasi" di halaman landing. Bisa link eksternal atau internal (misal: /mushaf-request)',
            'group' => 'landing',
            'sort_order' => 50,
            'is_public' => true,
            'is_active' => true,
        ];

        Setting::updateOrCreate(
            ['key' => $setting['key']],
            $setting
        );

        $this->command->info('✅ Landing donation link setting seeded successfully!');
        $this->command->info('   Key: '.$setting['key']);
        $this->command->info('   Default value: '.$setting['value']);
    }
}
