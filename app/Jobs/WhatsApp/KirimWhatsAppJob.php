<?php

namespace App\Jobs\WhatsApp;

use App\Models\WhatsAppNotification;
use App\Models\WhatsAppSetting;
use App\Services\WhatsApp\StarSenderClient;
use App\Services\WhatsApp\WhatsAppRateLimiter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim satu notifikasi WhatsApp yang sudah ada di antrean.
 *
 * Job ini sengaja TIDAK PERNAH melempar exception untuk kegagalan pengiriman —
 * kegagalan dicatat pada barisnya sendiri (status 'gagal' + pesan galat). Kalau
 * dilempar, barisnya akan tetap 'menunggu' dan pengguna hanya melihat job gagal
 * di tabel `failed_jobs` yang tidak mereka buka, padahal yang mereka butuhkan
 * adalah "pesan ini gagal, ini sebabnya, kirim ulang".
 *
 * Percobaan otomatis dibatasi dan hanya dipakai untuk kasus yang AMAN diulang
 * (pembatas laju). Penolakan dari penyedia tidak diulang: lihat catatan panjang
 * di StarSenderClient tentang risiko kiriman ganda.
 */
class KirimWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $notificationId) {}

    /**
     * Satu notifikasi hanya boleh diproses satu worker pada satu waktu.
     *
     * Command `whatsapp:kirim-tertunda` dan tombol "Kirim ulang" bisa
     * mengantrekan job kedua untuk notifikasi yang sama selagi job pertama masih
     * berjalan. Keduanya sama-sama melihat status belum 'terkirim' dan donatur
     * menerima dua pesan. Dengan kunci ini job kedua menunggu, lalu penjaga
     * `sudahSelesai()` di handle() menghentikannya.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->notificationId))
                ->releaseAfter(30)
                ->expireAfter(180),
        ];
    }

    public function handle(WhatsAppRateLimiter $pembatasLaju): void
    {
        $notifikasi = WhatsAppNotification::query()->find($this->notificationId);

        if (! $notifikasi) {
            return;
        }

        // Penjaga idempotensi: job yang dijalankan dua kali tidak boleh
        // mengirim pesan kedua.
        if ($notifikasi->sudahSelesai()) {
            return;
        }

        $setting = WhatsAppSetting::active();

        if (! $setting?->siapKirim()) {
            $notifikasi->update([
                'status' => 'dilewati',
                'error_message' => 'Notifikasi WhatsApp belum diaktifkan atau kunci API belum diisi.',
            ]);

            return;
        }

        // Batas laju: kembalikan ke antrean, bukan gagalkan. Ini satu-satunya
        // alasan yang pantas diulang otomatis.
        if (! $pembatasLaju->ambilSlot((int) $setting->max_per_minute)) {
            $this->release(30);

            return;
        }

        try {
            $hasil = StarSenderClient::dari($setting)->kirimTeks($notifikasi->recipient, $notifikasi->body);
        } catch (Throwable $e) {
            // Pertahanan terakhir: klien sudah menangkap kegagalan HTTP, jadi ini
            // hanya untuk kesalahan yang tidak terduga.
            Log::error('Notifikasi WhatsApp gagal tak terduga', [
                'notifikasi_id' => $notifikasi->id,
                'galat' => $e->getMessage(),
            ]);

            $notifikasi->tandaiGagal('Gagal tak terduga: '.$e->getMessage());

            return;
        }

        if ($hasil['sukses']) {
            $notifikasi->tandaiTerkirim($hasil['mentah']);

            return;
        }

        $notifikasi->tandaiGagal($hasil['pesan'], $hasil['mentah']);
    }
}
