<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Testimonial;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $testimonials = [
            [
                'name' => 'H. Ahmad Fauzi',
                'location' => 'Jakarta',
                'quote' => 'Alhamdulillah, mushaf Al-Quran telah sampai dengan selamat. Proses tracking yang transparan membuat kami tenang. Barakallahu fiikum.',
                'image' => null,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Ustadz Abdullah',
                'location' => 'Bandung',
                'quote' => 'Program Ekspedisi Quran ini sangat membantu pondok pesantren kami. Santri-santri jadi lebih semangat mengaji dengan mushaf yang baru.',
                'image' => null,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Hj. Siti Aminah',
                'location' => 'Surabaya',
                'quote' => 'Masyaa Allah, pelayanan sangat baik dan amanah. Sertifikat wakafnya juga sangat bagus. Semoga menjadi amal jariyah.',
                'image' => null,
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Bapak Ridwan',
                'location' => 'Medan',
                'quote' => 'Terima kasih Ekspedisi Quran, mushaf sudah diterima oleh masjid di kampung kami. Jamaah sangat senang dan bersyukur.',
                'image' => null,
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($testimonials as $testimonial) {
            Testimonial::firstOrCreate(
                ['name' => $testimonial['name'], 'quote' => $testimonial['quote']],
                $testimonial
            );
        }
    }
}