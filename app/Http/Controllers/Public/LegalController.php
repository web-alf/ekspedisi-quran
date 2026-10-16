<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Inertia\Inertia;

class LegalController extends Controller
{
    /**
     * Show Privacy Policy page
     */
    public function privacyPolicy()
    {
        // Get settings for the page
        $settings = Setting::where('is_public', true)
            ->where('is_active', true)
            ->whereIn('group', ['landing', 'contact', 'social', 'general', 'legal'])
            ->pluck('value', 'key');

        // Get privacy policy content
        $privacyPolicy = Setting::get('legal_privacy_policy', $this->getDefaultPrivacyPolicy());

        return Inertia::render('Public/PrivacyPolicy', [
            'settings' => $settings,
            'content' => $privacyPolicy,
            'lastUpdated' => Setting::get('legal_privacy_policy_updated', now()->format('d F Y'))
        ]);
    }

    /**
     * Show Terms of Service page
     */
    public function termsOfService()
    {
        // Get settings for the page
        $settings = Setting::where('is_public', true)
            ->where('is_active', true)
            ->whereIn('group', ['landing', 'contact', 'social', 'general', 'legal'])
            ->pluck('value', 'key');

        // Get terms of service content
        $termsOfService = Setting::get('legal_terms_of_service', $this->getDefaultTermsOfService());

        return Inertia::render('Public/TermsOfService', [
            'settings' => $settings,
            'content' => $termsOfService,
            'lastUpdated' => Setting::get('legal_terms_of_service_updated', now()->format('d F Y'))
        ]);
    }

    /**
     * Get default privacy policy content
     */
    private function getDefaultPrivacyPolicy()
    {
        return '
<h2>Kebijakan Privasi Ekspedisi Qur\'an</h2>

<p><strong>Terakhir diperbarui:</strong> ' . now()->format('d F Y') . '</p>

<h3>1. Informasi yang Kami Kumpulkan</h3>
<p>Kami mengumpulkan informasi yang Anda berikan kepada kami secara langsung, seperti:</p>
<ul>
    <li>Nama lengkap dan informasi kontak</li>
    <li>Alamat pengiriman yang lengkap</li>
    <li>Nomor telepon dan email</li>
    <li>Informasi lembaga atau institusi</li>
    <li>Data permintaan mushaf Al-Qur\'an</li>
</ul>

<h3>2. Bagaimana Kami Menggunakan Informasi Anda</h3>
<p>Informasi yang kami kumpulkan digunakan untuk:</p>
<ul>
    <li>Memproses dan mengirimkan permintaan mushaf Al-Qur\'an</li>
    <li>Berkomunikasi dengan Anda mengenai status pengiriman</li>
    <li>Memberikan layanan pelacakan pengiriman</li>
    <li>Meningkatkan kualitas layanan kami</li>
    <li>Mengirimkan sertifikat distribusi</li>
</ul>

<h3>3. Berbagi Informasi</h3>
<p>Kami tidak akan menjual, menyewakan, atau membagikan informasi pribadi Anda kepada pihak ketiga, kecuali:</p>
<ul>
    <li>Untuk keperluan pengiriman melalui jasa ekspedisi terpercaya</li>
    <li>Jika diwajibkan oleh hukum</li>
    <li>Untuk melindungi hak, properti, atau keamanan kami</li>
</ul>

<h3>4. Keamanan Data</h3>
<p>Kami menggunakan langkah-langkah keamanan yang sesuai untuk melindungi informasi pribadi Anda dari akses yang tidak sah, perubahan, pengungkapan, atau penghancuran.</p>

<h3>5. Hak Anda</h3>
<p>Anda memiliki hak untuk:</p>
<ul>
    <li>Mengakses informasi pribadi yang kami miliki tentang Anda</li>
    <li>Meminta koreksi informasi yang tidak akurat</li>
    <li>Meminta penghapusan informasi pribadi Anda</li>
    <li>Menarik persetujuan Anda kapan saja</li>
</ul>

<h3>6. Cookies</h3>
<p>Website kami menggunakan cookies untuk meningkatkan pengalaman Anda. Anda dapat mengatur browser Anda untuk menolak cookies, namun hal ini mungkin mempengaruhi fungsionalitas website.</p>

<h3>7. Perubahan Kebijakan</h3>
<p>Kami dapat memperbarui kebijakan privasi ini dari waktu ke waktu. Perubahan akan diumumkan di halaman ini dengan tanggal pembaruan yang baru.</p>

<h3>8. Hubungi Kami</h3>
<p>Jika Anda memiliki pertanyaan tentang kebijakan privasi ini, silakan hubungi kami di:</p>
<ul>
    <li>Email: privacy@ekspedisiquran.com</li>
    <li>Alamat: Semarang, Indonesia</li>
</ul>
        ';
    }

    /**
     * Get default terms of service content
     */
    private function getDefaultTermsOfService()
    {
        return '
<h2>Syarat dan Ketentuan Layanan Ekspedisi Qur\'an</h2>

<p><strong>Terakhir diperbarui:</strong> ' . now()->format('d F Y') . '</p>

<h3>1. Penerimaan Syarat</h3>
<p>Dengan menggunakan layanan Ekspedisi Qur\'an, Anda menyetujui untuk terikat oleh syarat dan ketentuan ini. Jika Anda tidak menyetujui syarat ini, harap tidak menggunakan layanan kami.</p>

<h3>2. Tentang Layanan Kami</h3>
<p>Ekspedisi Qur\'an adalah program distribusi mushaf Al-Qur\'an secara gratis kepada lembaga-lembaga pendidikan, masjid, pesantren, dan institusi keagamaan di seluruh Indonesia.</p>

<h3>3. Eligibilitas</h3>
<p>Layanan kami ditujukan untuk:</p>
<ul>
    <li>Lembaga pendidikan Islam</li>
    <li>Masjid dan mushola</li>
    <li>Pesantren dan madrasah</li>
    <li>Yayasan dan organisasi keagamaan</li>
    <li>Komunitas dan panti asuhan</li>
</ul>

<h3>4. Proses Permohonan</h3>
<p>Untuk mengajukan permohonan mushaf Al-Qur\'an:</p>
<ul>
    <li>Lengkapi formulir permohonan dengan data yang benar</li>
    <li>Sertakan informasi kontak yang valid</li>
    <li>Berikan alamat pengiriman yang akurat dan lengkap</li>
    <li>Tunggu konfirmasi dari tim kami</li>
</ul>

<h3>5. Verifikasi dan Persetujuan</h3>
<p>Kami berhak untuk:</p>
<ul>
    <li>Memverifikasi keabsahan lembaga yang mengajukan permohonan</li>
    <li>Menolak permohonan yang tidak memenuhi kriteria</li>
    <li>Membatasi jumlah mushaf yang dikirim berdasarkan kebutuhan</li>
    <li>Meminta dokumentasi tambahan jika diperlukan</li>
</ul>

<h3>6. Pengiriman</h3>
<p>Ketentuan pengiriman:</p>
<ul>
    <li>Pengiriman dilakukan secara gratis ke seluruh Indonesia</li>
    <li>Waktu pengiriman bervariasi tergantung lokasi</li>
    <li>Kami tidak bertanggung jawab atas keterlambatan yang disebabkan force majeure</li>
    <li>Penerima bertanggung jawab memastikan alamat pengiriman dapat diakses kurir</li>
</ul>

<h3>7. Kewajiban Penerima</h3>
<p>Sebagai penerima, Anda berkomitmen untuk:</p>
<ul>
    <li>Menggunakan mushaf Al-Qur\'an untuk tujuan pendidikan dan dakwah</li>
    <li>Tidak menjual atau memperjualbelikan mushaf yang diterima</li>
    <li>Merawat dan menjaga mushaf dengan baik</li>
    <li>Memberikan laporan penggunaan jika diminta</li>
</ul>

<h3>8. Larangan</h3>
<p>Dilarang keras untuk:</p>
<ul>
    <li>Menggunakan layanan untuk tujuan komersial</li>
    <li>Memberikan informasi palsu atau menyesatkan</li>
    <li>Menduplikasi permohonan dengan identitas berbeda</li>
    <li>Menyalahgunakan mushaf untuk hal-hal yang tidak sesuai</li>
</ul>

<h3>9. Pelacakan dan Sertifikat</h3>
<p>Kami menyediakan:</p>
<ul>
    <li>Sistem pelacakan pengiriman real-time</li>
    <li>Sertifikat distribusi setelah pengiriman selesai</li>
    <li>Dukungan customer service untuk pertanyaan</li>
</ul>

<h3>10. Pembatasan Tanggung Jawab</h3>
<p>Ekspedisi Qur\'an tidak bertanggung jawab atas:</p>
<ul>
    <li>Kerusakan yang terjadi setelah penyerahan kepada penerima</li>
    <li>Kerugian tidak langsung atau konsekuensial</li>
    <li>Gangguan layanan karena masalah teknis</li>
</ul>

<h3>11. Perubahan Syarat</h3>
<p>Kami berhak mengubah syarat dan ketentuan ini kapan saja. Perubahan akan diumumkan di website kami dan berlaku sejak tanggal publikasi.</p>

<h3>12. Hukum yang Berlaku</h3>
<p>Syarat dan ketentuan ini diatur oleh dan ditafsirkan sesuai dengan hukum Republik Indonesia.</p>

<h3>13. Kontak</h3>
<p>Untuk pertanyaan mengenai syarat dan ketentuan ini, hubungi kami di:</p>
<ul>
    <li>Email: legal@ekspedisiquran.com</li>
    <li>Website: www.ekspedisiquran.com</li>
    <li>Alamat: Semarang, Indonesia</li>
</ul>
        ';
    }
}
