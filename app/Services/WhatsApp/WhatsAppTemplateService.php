<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Log;

/**
 * Menyusun isi pesan dari template + data peristiwa.
 *
 * Sengaja TIDAK `final` — berbeda dari layanan WhatsApp lain di folder ini —
 * supaya bisa diganti di uji. Uji "notifikasi tidak boleh merusak alur status"
 * perlu memaksa penyusunan pesan gagal, dan Mockery tidak bisa mem-mock kelas
 * final.
 */
class WhatsAppTemplateService
{
    /**
     * @param  array<string, string|int|float|null>  $data
     */
    public function susun(WhatsAppTemplate $template, array $data): string
    {
        $isi = $template->content;

        foreach ($data as $kunci => $nilai) {
            $isi = str_replace('{'.$kunci.'}', (string) $nilai, $isi);
        }

        // Placeholder yang tidak terisi JANGAN sampai terlihat wakif: pesan
        // berisi "{nama}" terbaca seperti sistem rusak. Sisa placeholder dibuang
        // dan dicatat supaya kesalahan template ketahuan, bukan disembunyikan.
        if (preg_match_all('/\{[a-z_]+\}/i', $isi, $cocok) > 0) {
            Log::warning('Template WhatsApp punya placeholder yang tidak terisi', [
                'template' => $template->name,
                'placeholder' => $cocok[0],
            ]);

            $isi = preg_replace('/\{[a-z_]+\}/i', '', $isi) ?? $isi;
        }

        return trim($isi);
    }

    /**
     * Data contoh untuk pratinjau di halaman pengaturan — supaya pengguna bisa
     * melihat hasil akhir pesan tanpa mengirim apa pun.
     *
     * @return array<string, string>
     */
    public function dataContoh(): array
    {
        return [
            'nama' => 'Ahmad Fauzi',
            'jumlah' => '2',
            'batch' => 'WB-2026-00001',
            'resi' => 'EQ-2026-00001',
            'sertifikat' => 'CERT-EQ-2026-00001',
            'lembaga' => 'Masjid Al-Ikhlas',
            'link' => rtrim((string) config('app.url'), '/').'/tracking/EQ-2026-00001',
        ];
    }
}
