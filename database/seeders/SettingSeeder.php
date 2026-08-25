<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Landing Page Settings
            [
                'key' => 'landing_title',
                'label' => 'Judul Halaman Utama',
                'value' => 'Ekspedisi Qur\'an',
                'type' => 'text',
                'description' => 'Judul utama yang ditampilkan di halaman utama',
                'group' => 'landing',
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'landing_subtitle',
                'label' => 'Subtitle Halaman Utama',
                'value' => 'Distribusi Al-Qur\'an untuk Seluruh Indonesia',
                'type' => 'text',
                'description' => 'Subtitle yang ditampilkan di bawah judul utama',
                'group' => 'landing',
                'sort_order' => 2,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'landing_description',
                'label' => 'Deskripsi Halaman Utama',
                'value' => 'Platform distribusi Al-Qur\'an yang menghubungkan donatur dengan lembaga-lembaga pendidikan Islam di seluruh Indonesia. Bergabunglah dalam misi mulia menyebarkan Al-Qur\'an ke pelosok nusantara.',
                'type' => 'textarea',
                'description' => 'Deskripsi lengkap tentang platform ini',
                'group' => 'landing',
                'sort_order' => 3,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'landing_hero_image',
                'label' => 'Gambar Hero Halaman Utama',
                'value' => '/images/hero-ekspedisi-quran.webp',
                'type' => 'image',
                'description' => 'Gambar besar yang ditampilkan di bagian hero halaman utama',
                'group' => 'landing',
                'sort_order' => 4,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'landing_stats_enabled',
                'label' => 'Tampilkan Statistik',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Tampilkan atau sembunyikan bagian statistik di halaman utama',
                'group' => 'landing',
                'sort_order' => 5,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'landing_map_enabled',
                'label' => 'Tampilkan Peta Distribusi',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Tampilkan atau sembunyikan peta distribusi di halaman utama',
                'group' => 'landing',
                'sort_order' => 6,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'landing_gallery_enabled',
                'label' => 'Enable Gallery Section',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Show or hide gallery section on landing page',
                'group' => 'gallery',
                'sort_order' => 8,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'landing_faqs_enabled',
                'label' => 'Tampilkan FAQ di Landing Page',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Enable FAQ section on landing page',
                'group' => 'faq',
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'landing_testimonials_enabled',
                'label' => 'Tampilkan Testimonials di Landing Page',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Enable testimonials section on landing page',
                'group' => 'testimonial',
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true
            ],
            
            // Contact Information
            [
                'key' => 'contact_email',
                'label' => 'Email Kontak',
                'value' => 'info@ekspedisiquran.org',
                'type' => 'text',
                'description' => 'Alamat email untuk kontak',
                'group' => 'contact',
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'contact_phone',
                'label' => 'Nomor Telepon',
                'value' => '+62 21 1234 5678',
                'type' => 'text',
                'description' => 'Nomor telepon untuk kontak',
                'group' => 'contact',
                'sort_order' => 2,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'contact_address',
                'label' => 'Alamat',
                'value' => 'Jl. Contoh No. 123, Jakarta, Indonesia',
                'type' => 'textarea',
                'description' => 'Alamat lengkap organisasi',
                'group' => 'contact',
                'sort_order' => 3,
                'is_public' => true,
                'is_active' => true
            ],
            
            // Social Media
            [
                'key' => 'social_facebook',
                'label' => 'Facebook URL',
                'value' => null,
                'type' => 'text',
                'description' => 'Link ke halaman Facebook',
                'group' => 'social',
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'social_instagram',
                'label' => 'Instagram URL',
                'value' => null,
                'type' => 'text',
                'description' => 'Link ke halaman Instagram',
                'group' => 'social',
                'sort_order' => 2,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'social_twitter',
                'label' => 'Twitter URL',
                'value' => null,
                'type' => 'text',
                'description' => 'Link ke halaman Twitter',
                'group' => 'social',
                'sort_order' => 3,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'social_youtube',
                'label' => 'YouTube URL',
                'value' => null,
                'type' => 'text',
                'description' => 'Link ke channel YouTube',
                'group' => 'social',
                'sort_order' => 4,
                'is_public' => true,
                'is_active' => true
            ],
            
            // SEO Settings
            [
                'key' => 'seo_meta_title',
                'label' => 'Meta Title',
                'value' => 'Ekspedisi Qur\'an - Distribusi Al-Qur\'an untuk Indonesia',
                'type' => 'text',
                'description' => 'Judul meta untuk SEO',
                'group' => 'seo',
                'sort_order' => 1,
                'is_public' => false,
                'is_active' => true
            ],
            [
                'key' => 'seo_meta_description',
                'label' => 'Meta Description',
                'value' => 'Platform distribusi Al-Qur\'an yang menghubungkan donatur dengan lembaga pendidikan Islam di seluruh Indonesia.',
                'type' => 'textarea',
                'description' => 'Deskripsi meta untuk SEO',
                'group' => 'seo',
                'sort_order' => 2,
                'is_public' => false,
                'is_active' => true
            ],
            [
                'key' => 'seo_meta_keywords',
                'label' => 'Meta Keywords',
                'value' => 'al-quran, distribusi, donasi, wakaf, pendidikan islam, indonesia',
                'type' => 'text',
                'description' => 'Kata kunci meta untuk SEO',
                'group' => 'seo',
                'sort_order' => 3,
                'is_public' => false,
                'is_active' => true
            ],
            
            // General Settings
            [
                'key' => 'app_name',
                'label' => 'Nama Aplikasi',
                'value' => 'Ekspedisi Qur\'an',
                'type' => 'text',
                'description' => 'Nama aplikasi yang ditampilkan di seluruh sistem',
                'group' => 'general',
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'app_logo',
                'label' => 'Logo Aplikasi',
                'value' => null,
                'type' => 'image',
                'description' => 'Logo aplikasi yang ditampilkan di header',
                'group' => 'general',
                'sort_order' => 2,
                'is_public' => true,
                'is_active' => true
            ],
            [
                'key' => 'admin_logo',
                'label' => 'Logo Admin (Putih)',
                'value' => null,
                'type' => 'image',
                'description' => 'Logo putih untuk sidebar admin dan footer dengan background gelap',
                'group' => 'general',
                'sort_order' => 3,
                'is_public' => true,
                'is_active' => true
            ]
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}