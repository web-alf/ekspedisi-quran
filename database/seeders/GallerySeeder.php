<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gallery;

class GallerySeeder extends Seeder
{
    public function run(): void
    {
        $galleries = [
            [
                'title' => 'Packing Mushaf Al-Quran',
                'caption' => 'Packing mushaf dengan penuh perhatian dan kehati-hatian',
                'image' => 'images/galleries/distribusi 1.webp',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Perjalanan Menuju Pelosok',
                'caption' => 'Perjalanan panjang menuju pelosok Nusantara',
                'image' => 'images/galleries/distribusi 2.webp',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Penyerahan ke Masjid',
                'caption' => 'Penyerahan langsung ke masjid dan pesantren',
                'image' => 'images/galleries/distribusi 3.webp',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'title' => 'Senyum Bahagia Penerima',
                'caption' => 'Senyum bahagia penerima mushaf Al-Quran',
                'image' => 'images/galleries/distribusi 4.webp',
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($galleries as $gallery) {
            Gallery::firstOrCreate(
                ['image' => $gallery['image']],
                $gallery
            );
        }
    }
}
