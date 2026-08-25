<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SystemSetting;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // App Configuration
            [
                'key' => 'app_name',
                'value' => 'Ekspedisi Quran',
                'description' => 'Nama aplikasi',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'key' => 'app_description',
                'value' => 'Sistem Tracking Wakaf Al-Quran',
                'description' => 'Deskripsi aplikasi', 
                'type' => 'string',
                'is_public' => true,
            ],
            // Organization Info
            [
                'key' => 'organization_name',
                'value' => 'Yayasan Wakaf Quran Indonesia',
                'description' => 'Nama organisasi',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'key' => 'contact_phone',
                'value' => '021-12345678',
                'description' => 'Nomor telepon kontak',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'key' => 'contact_email',
                'value' => 'info@ekspedisiquran.com',
                'description' => 'Email kontak',
                'type' => 'string',
                'is_public' => true,
            ],
            // System Settings
            [
                'key' => 'resi_prefix',
                'value' => 'EQ',
                'description' => 'Prefix untuk nomor resi',
                'type' => 'string',
                'is_public' => false,
            ],
            [
                'key' => 'max_upload_size',
                'value' => '5120',
                'description' => 'Maksimal ukuran file upload dalam KB',
                'type' => 'number',
                'is_public' => false,
            ]
        ];

        // Use updateOrCreate to avoid duplicates
        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']], // Find by key
                $setting // Update or create with this data
            );
        }
        
        $this->command->info('✅ System settings seeded successfully!');
    }
}
