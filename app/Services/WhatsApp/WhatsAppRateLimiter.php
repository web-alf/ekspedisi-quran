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

    public function hitungKirim(): int
    {
        $kunci = $this->kunci();
        $baru = $this->jumlahMenitIni() + 1;

        // TTL sedikit lebih panjang dari satu menit supaya kuncinya tidak
        // kedaluwarsa tepat di tengah penilaian pada detik ke-59.
        Cache::put($kunci, $baru, now()->addMinutes(2));

        return $baru;
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
