<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SeoSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $seoSettings = [
            [
                'key' => 'seo_site_title',
                'label' => 'Judul Website',
                'value' => "Ekspedisi Qur'an",
                'type' => 'text',
                'description' => 'Judul utama website yang akan muncul di social media dan tab browser',
                'group' => 'seo',
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true,
            ],
            [
                'key' => 'seo_site_description',
                'label' => 'Deskripsi Website',
                'value' => 'Platform wakaf dan distribusi mushaf Al-Quran untuk seluruh Indonesia',
                'type' => 'textarea',
                'description' => 'Deskripsi website yang akan muncul di hasil pencarian dan social media',
                'group' => 'seo',
                'sort_order' => 2,
                'is_public' => true,
                'is_active' => true,
            ],
            [
                'key' => 'seo_og_image',
                'label' => 'Gambar Social Media (Open Graph)',
                'value' => 'images/og-image.jpg',
                'type' => 'file',
                'description' => 'Gambar yang akan muncul saat link website dibagikan di social media (1200x630px)',
                'options' => json_encode([
                    'accept' => 'image/jpeg,image/jpg,image/png,image/webp',
                    'max_size' => '2048', // 2MB
                    'dimensions' => ['width' => 1200, 'height' => 630]
                ]),
                'group' => 'seo',
                'sort_order' => 3,
                'is_public' => true,
                'is_active' => true,
            ],
            [
                'key' => 'seo_facebook_url',
                'label' => 'URL Facebook',
                'value' => 'https://www.facebook.com/ekspedisiquran/',
                'type' => 'url',
                'description' => 'Link halaman Facebook resmi',
                'group' => 'seo',
                'sort_order' => 4,
                'is_public' => true,
                'is_active' => true,
            ],
            [
                'key' => 'seo_instagram_url',
                'label' => 'URL Instagram',
                'value' => 'https://www.instagram.com/ekspedisiquran/',
                'type' => 'url',
                'description' => 'Link akun Instagram resmi',
                'group' => 'seo',
                'sort_order' => 5,
                'is_public' => true,
                'is_active' => true,
            ],
            [
                'key' => 'seo_author',
                'label' => 'Nama Penulis/Organisasi',
                'value' => "Ekspedisi Qur'an",
                'type' => 'text',
                'description' => 'Nama penulis atau organisasi untuk meta author',
                'group' => 'seo',
                'sort_order' => 6,
                'is_public' => true,
                'is_active' => true,
            ],
        ];

        foreach ($seoSettings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']], 
                $setting
            );
        }

        $this->command->info('✅ SEO settings seeded successfully!');
    }
}
