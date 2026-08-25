<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faq;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'Apa itu Program Ekspedisi Quran?',
                'answer' => "Program Ekspedisi Quran adalah inisiatif wakaf untuk mendistribusikan mushaf Al-Quran ke berbagai pelosok Indonesia. Program ini bertujuan memastikan setiap muslim memiliki akses terhadap kitab suci Al-Quran.\n\nKami bekerja sama dengan para wakif untuk mengumpulkan dana, kemudian mendistribusikan mushaf ke masjid, pesantren, dan komunitas yang membutuhkan di seluruh Nusantara.",
                'category' => 'general',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'question' => 'Bagaimana cara berpartisipasi dalam program wakaf?',
                'answer' => "Anda dapat berpartisipasi melalui beberapa cara:\n\n1. Wakaf Mushaf: Minimal Rp 50.000 per mushaf\n2. Wakaf Paket: Tersedia paket 10, 50, atau 100 mushaf\n3. Wakaf Terbuka: Berapapun nominal yang Anda wakafkan akan kami akumulasikan\n\nSilakan hubungi kami melalui WhatsApp atau datang langsung ke kantor kami untuk informasi lebih lanjut.",
                'category' => 'donation',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'question' => 'Bagaimana cara melacak pengiriman mushaf?',
                'answer' => "Setiap pengiriman dilengkapi dengan kode QR unik yang dapat Anda scan untuk melacak status pengiriman secara real-time. Anda juga akan menerima notifikasi WhatsApp untuk setiap perubahan status.\n\nCara melacak:\n1. Scan QR code pada sertifikat wakaf Anda\n2. Atau masukkan nomor tracking di halaman pelacakan\n3. Lihat status terkini dan dokumentasi pengiriman",
                'category' => 'tracking',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'question' => 'Berapa lama proses pengiriman mushaf?',
                'answer' => "Waktu pengiriman bervariasi tergantung lokasi tujuan:\n\n- Pulau Jawa: 3-7 hari kerja\n- Sumatera & Kalimantan: 7-14 hari kerja\n- Sulawesi & Papua: 14-21 hari kerja\n- Daerah terpencil: 21-30 hari kerja\n\nKami akan menginformasikan estimasi waktu yang lebih akurat saat konfirmasi pengiriman.",
                'category' => 'distribution',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'question' => 'Apakah saya akan mendapat sertifikat wakaf?',
                'answer' => "Ya, setiap wakif akan mendapatkan sertifikat wakaf digital yang dapat diunduh melalui sistem kami. Sertifikat ini berisi:\n\n- Nama wakif\n- Jumlah mushaf yang diwakafkan\n- Tanggal wakaf\n- QR code untuk tracking\n- Nomor sertifikat unik\n\nSertifikat akan tersedia setelah pembayaran dikonfirmasi.",
                'category' => 'general',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'question' => 'Bagaimana memastikan mushaf sampai ke tangan yang tepat?',
                'answer' => "Kami memiliki sistem verifikasi berlapis:\n\n1. Verifikasi penerima melalui data lembaga/masjid\n2. Dokumentasi foto saat penyerahan\n3. Tanda tangan & stempel penerima\n4. Koordinasi dengan tokoh masyarakat setempat\n5. Follow up pasca pengiriman\n\nSemua dokumentasi dapat Anda lihat melalui sistem tracking kami.",
                'category' => 'distribution',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'question' => 'Apakah ada minimal jumlah untuk wakaf mushaf?',
                'answer' => "Tidak ada minimal jumlah mushaf. Anda dapat berwakaf mulai dari 1 mushaf saja. Kami juga menyediakan program wakaf terbuka di mana dana dari beberapa wakif digabungkan untuk pengadaan mushaf.\n\nUntuk wakaf dalam jumlah besar (>1000 mushaf), kami dapat memberikan penawaran khusus dan program distribusi yang disesuaikan.",
                'category' => 'donation',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'question' => 'Bagaimana jika ada masalah dengan pengiriman?',
                'answer' => "Jika terjadi masalah dengan pengiriman, segera hubungi kami melalui:\n\n1. WhatsApp Customer Service\n2. Email: support@ekspedisiquran.com\n3. Telepon kantor pada jam kerja\n\nKami akan segera menindaklanjuti dan memberikan solusi terbaik. Setiap pengiriman diasuransikan untuk memastikan mushaf sampai dengan selamat.",
                'category' => 'technical',
                'sort_order' => 8,
                'is_active' => true,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::firstOrCreate(
                ['question' => $faq['question']],
                $faq
            );
        }
    }
}