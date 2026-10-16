<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Cache;

/**
 * Pembatas laju kirim, agar nomor pengirim tidak diblokir WhatsApp.
 *
 * Batasnya per menit (kolom `max_per_minute` di pengaturan) dan dihitung di
 * cache, bukan di database: hitungannya sementara dan tidak perlu bertahan
 * setelah satu menit lewat. Bergantung pada store cache yang aktif — store
 * `database` dan `file` sama-sama bisa.
 */
final class WhatsAppRateLimiter
{
    public function bolehKirim(int $maksPerMenit): bool
    {
        if ($maksPerMenit <= 0) {
            return true;
        }

        return $this->jumlahMenitIni() < $maksPerMenit;
    }

    /**
     * Pesan satu slot kirim menit ini secara atomik, SEBELUM mengirim.
     *
     * Pola lama "cek bolehKirim, kirim, lalu hitungKirim" membiarkan N worker
     * lolos cek bersamaan dan semuanya mengirim, sehingga batas per menit
     * terlampaui. Di sini penghitung dinaikkan dulu (increment atomik di store
     * cache), dan hanya worker yang mendapat nomor di dalam batas yang boleh kirim.
     */
    public function ambilSlot(int $maksPerMenit): bool
    {
        if ($maksPerMenit <= 0) {
            return true;
        }

        return $this->hitungKirim() <= $maksPerMenit;
    }

    public function hitungKirim(): int
    {
        $kunci = $this->kunci();

        // TTL sedikit lebih panjang dari satu menit supaya kuncinya tidak
        // kedaluwarsa tepat di tengah penilaian pada detik ke-59. `add` hanya
        // membuat kunci bila belum ada; `increment` atomik, tidak seperti
        // get-lalu-put yang kehilangan hitungan saat dipanggil bersamaan.
        Cache::add($kunci, 0, now()->addMinutes(2));

        return (int) Cache::increment($kunci);
    }

    private function jumlahMenitIni(): int
    {
        return (int) Cache::get($this->kunci(), 0);
    }

    private function kunci(): string
    {
        return 'whatsapp:laju:'.now()->format('YmdHi');
    }
}
