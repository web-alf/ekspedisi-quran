<?php

namespace App\Services\WhatsApp;

use App\Jobs\WhatsApp\KirimWhatsAppJob;
use App\Models\WhatsAppNotification;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pintu masuk semua notifikasi WhatsApp.
 *
 * Aturan yang dipegang di sini:
 *
 * 1. Notifikasi TIDAK PERNAH menggagalkan alur utama. Semua kegagalan diubah
 *    menjadi baris antrean berstatus 'gagal'/'dilewati' yang bisa diperiksa,
 *    bukan exception yang membatalkan perubahan status resi.
 * 2. Pengiriman tidak pernah terjadi di dalam transaksi. Barisnya ditulis dulu,
 *    lalu job diantrekan dengan `afterCommit()` — kalau tidak, worker bisa
 *    mengambil job sebelum barisnya ter-commit dan tidak menemukan apa-apa.
 * 3. Idempoten lewat `dedupe_key`. Peristiwa yang sama untuk penerima yang sama
 *    hanya menghasilkan satu pesan, walau alurnya dijalankan dua kali.
 */
final class WhatsAppNotifier
{
    public function __construct(
        private readonly WhatsAppTemplateService $templateService,
    ) {}

    /**
     * @param  array<string, string|int|float|null>  $data  Isi placeholder template.
     * @param  array{donatur_id?: int|null, pengiriman_id?: int|null, wakaf_batch_id?: int|null, nama?: string|null}  $konteks
     */
    public function antri(string $eventKey, string $dedupeKey, ?string $nomor, array $data = [], array $konteks = []): ?WhatsAppNotification
    {
        // Pertahanan utama aturan "notifikasi tidak pernah menggagalkan alur
        // utama": apa pun yang salah di sini — termasuk galat database — tidak
        // boleh sampai ke pemanggil, karena pemanggilnya sedang mengubah status
        // resi yang sedang dikerjakan staf gudang. Dicatat sebagai galat supaya
        // tetap ketahuan, tapi tidak dilempar.
        try {
            return $this->antriDalam($eventKey, $dedupeKey, $nomor, $data, $konteks);
        } catch (Throwable $e) {
            Log::error('Notifikasi WhatsApp gagal diantrekan', [
                'peristiwa' => $eventKey,
                'kunci' => $dedupeKey,
                'galat' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, string|int|float|null>  $data
     * @param  array{donatur_id?: int|null, pengiriman_id?: int|null, wakaf_batch_id?: int|null, nama?: string|null}  $konteks
     */
    private function antriDalam(string $eventKey, string $dedupeKey, ?string $nomor, array $data, array $konteks): ?WhatsAppNotification
    {
        $template = WhatsAppTemplate::untuk($eventKey);

        // Peristiwa yang tidak punya baris template berarti belum didukung.
        // Tidak dicatat: kalau dicatat, setiap penambahan peristiwa baru akan
        // mengotori antrean dengan baris 'dilewati' yang tidak berarti.
        if (! $template) {
            return null;
        }

        $sudahAda = WhatsAppNotification::query()->where('dedupe_key', $dedupeKey)->first();

        if ($sudahAda) {
            return $sudahAda;
        }

        $penyebab = $this->alasanDilewati($template, $nomor);

        $atribut = [
            'event_key' => $eventKey,
            'dedupe_key' => $dedupeKey,
            'template_id' => $template->id,
            'donatur_id' => $konteks['donatur_id'] ?? null,
            'pengiriman_id' => $konteks['pengiriman_id'] ?? null,
            'wakaf_batch_id' => $konteks['wakaf_batch_id'] ?? null,
            'recipient' => $nomor ?: '-',
            'recipient_name' => $konteks['nama'] ?? null,
            'body' => $this->templateService->susun($template, $data),
            'status' => $penyebab === null ? 'menunggu' : 'dilewati',
            // Diisi eksplisit, bukan mengandalkan default kolom: setelah
            // `create()` objek di memori belum tahu nilai default database,
            // sehingga pembacaan `attempts` sebelum refresh akan mengembalikan
            // null — dan `null + 1` menyembunyikan maksud aslinya.
            'attempts' => 0,
            'error_message' => $penyebab,
        ];

        try {
            $notifikasi = WhatsAppNotification::create($atribut);
        } catch (UniqueConstraintViolationException) {
            // Dua proses menulis peristiwa yang sama bersamaan; yang kalah
            // memakai baris yang sudah dibuat pemenangnya.
            return WhatsAppNotification::query()->where('dedupe_key', $dedupeKey)->first();
        }

        if ($penyebab === null && ! $this->sedangJamTenang()) {
            KirimWhatsAppJob::dispatch($notifikasi->id)->afterCommit();
        }

        return $notifikasi;
    }

    /**
     * Alasan pesan tidak dikirim, atau null bila layak dikirim.
     *
     * Bedanya penting untuk pengguna: 'dilewati' karena template dimatikan
     * adalah keputusan sadar yang ingin dilihat di antrean, sedangkan nomor yang
     * kosong adalah kekurangan data donatur.
     */
    private function alasanDilewati(WhatsAppTemplate $template, ?string $nomor): ?string
    {
        if (! $template->is_active) {
            return 'Template peristiwa ini sedang dimatikan.';
        }

        if (blank($nomor)) {
            return 'Nomor WhatsApp penerima belum ada di data donatur.';
        }

        if (! WhatsAppSetting::active()?->siapKirim()) {
            return 'Notifikasi WhatsApp belum diaktifkan atau kunci API belum diisi.';
        }

        return null;
    }

    private function sedangJamTenang(): bool
    {
        return WhatsAppSetting::active()?->sedangJamTenang() ?? false;
    }
}
