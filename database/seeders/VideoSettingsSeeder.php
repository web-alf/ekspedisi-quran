<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class VideoSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'landing_video_enabled',
                'label' => 'Tampilkan Video di Landing Page',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Aktifkan/nonaktifkan section video di halaman utama',
                'group' => 'video',
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'landing_video_title',
                'label' => 'Judul Section Video',
                'value' => 'Video Ekspedisi',
                'type' => 'text',
                'description' => 'Judul untuk section video di landing page',
                'group' => 'video',
                'sort_order' => 2,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'landing_video_subtitle',
                'label' => 'Subtitle Section Video',
                'value' => 'Dokumentasi video perjalanan mushaf Al-Qur\'an',
                'type' => 'textarea',
                'description' => 'Subtitle/deskripsi untuk section video',
                'group' => 'video',
                'sort_order' => 3,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'landing_video_layout',
                'label' => 'Layout Video',
                'value' => 'grid',
                'type' => 'select',
                'description' => 'Pilihan layout tampilan video: grid atau slider',
                'group' => 'video',
                'sort_order' => 4,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'landing_video_show_captions',
                'label' => 'Tampilkan Caption',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Tampilkan caption/judul video di bawah thumbnail',
                'group' => 'video',
                'sort_order' => 5,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'landing_video_lightbox',
                'label' => 'Enable Lightbox',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Aktifkan lightbox modal untuk memutar video',
                'group' => 'video',
                'sort_order' => 6,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'landing_video_max_items',
                'label' => 'Maksimal Video Ditampilkan',
                'value' => '6',
                'type' => 'number',
                'description' => 'Jumlah maksimal video yang ditampilkan di landing page (0 = semua)',
                'group' => 'video',
                'sort_order' => 7,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'landing_about_video_url',
                'label' => 'Video Tentang Program (YouTube)',
                'value' => 'https://youtu.be/3_mxkdlLL8Y?si=Qhr1VIjv78FYh7iP',
                'type' => 'text',
                'description' => 'URL YouTube untuk video tentang program di section Tentang. Kosongkan untuk menggunakan video default.',
                'group' => 'video',
                'sort_order' => 8,
                'is_public' => true,
                'is_active' => true
            ],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('Video settings seeded successfully!');
    }
}
