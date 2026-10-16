<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pembungkus HTTP StarSender.
 *
 * Bentuk permintaannya diverifikasi ulang 10 Okt 2026 pada dokumentasi resmi
 * StarSender V3 dan masih sama seperti dokumen lama di repo ini:
 *   POST {base_url}/api/send        header Authorization: <Device API key>
 *   body: messageType, to, body, file, delay, schedule
 *   balasan: {"success": true, "data": {}, "message": "Success sent message"}
 *
 * SENGAJA TIDAK ADA PERCOBAAN ULANG OTOMATIS untuk pengiriman. StarSender tidak
 * menyediakan kunci idempotensi, jadi bila balasannya hilang di tengah jalan
 * (koneksi putus setelah pesan benar-benar terkirim) percobaan ulang akan
 * mengirim pesan KEDUA ke orang yang sama. Untuk pesan yang dilihat manusia,
 * kiriman ganda lebih buruk daripada kiriman yang gagal lalu dikirim ulang
 * dengan sengaja dari halaman antrean. Karena itu kegagalan selalu dicatat dan
 * diserahkan ke manusia, bukan diulang sendiri.
 */
final class StarSenderClient
{
    public function __construct(private readonly WhatsAppSetting $setting) {}

    public static function dari(WhatsAppSetting $setting): self
    {
        return new self($setting);
    }

    /**
     * Kirim satu pesan teks.
     *
     * @return array{sukses: bool, pesan: string, kode_http: int|null, mentah: array<string, mixed>}
     */
    public function kirimTeks(string $tujuan, string $isi): array
    {
        return $this->kirim([
            'messageType' => 'text',
            'to' => $this->normalkanNomor($tujuan),
            'body' => $isi,
            'delay' => (int) $this->setting->delay_seconds,
        ]);
    }

    /**
     * Cek apakah nomor benar-benar terdaftar WhatsApp.
     *
     * Berguna untuk melewati nomor mati sebelum benar-benar mengirim: mengirim
     * ke nomor tidak terdaftar membebani kuota dan menambah risiko nomor
     * pengirim dinilai sebagai pengirim sampah. Aman diulang, jadi boleh retry.
     *
     * @return array{sukses: bool, pesan: string, kode_http: int|null, mentah: array<string, mixed>}
     */
    public function cekNomor(string $nomor): array
    {
        try {
            $respons = Http::withHeaders($this->header())
                ->timeout(15)
                ->retry(2, 300, throw: false)
                ->post($this->url('/api/check-number'), ['number' => $this->normalkanNomor($nomor)]);
        } catch (Throwable $e) {
            return $this->hasilGagal($e->getMessage(), null);
        }

        return $this->normalkanRespons($respons->successful(), $respons->status(), (array) $respons->json(), 'Nomor tidak terdaftar WhatsApp.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{sukses: bool, pesan: string, kode_http: int|null, mentah: array<string, mixed>}
     */
    private function kirim(array $payload): array
    {
        try {
            $respons = Http::withHeaders($this->header())
                // Tanpa `retry()`: lihat catatan di atas.
                ->timeout(20)
                ->post($this->url('/api/send'), $payload);
        } catch (Throwable $e) {
            Log::warning('StarSender tidak bisa dihubungi', [
                'tujuan' => $payload['to'] ?? null,
                'galat' => $e->getMessage(),
            ]);

            return $this->hasilGagal('Tidak bisa menghubungi StarSender: '.$e->getMessage(), null);
        }

        $mentah = (array) $respons->json();

        $hasil = $this->normalkanRespons(
            $respons->successful(),
            $respons->status(),
            $mentah,
            'StarSender menolak pengiriman (HTTP '.$respons->status().').'
        );

        if (! $hasil['sukses']) {
            Log::warning('StarSender menolak pengiriman', [
                'tujuan' => $payload['to'] ?? null,
                'kode_http' => $respons->status(),
                'balasan' => $mentah,
            ]);
        }

        return $hasil;
    }

    /**
     * StarSender membalas 200 walau gagal, dengan `success: false` — jadi kode
     * HTTP saja tidak cukup untuk menyimpulkan berhasil.
     *
     * @param  array<string, mixed>  $mentah
     * @return array{sukses: bool, pesan: string, kode_http: int|null, mentah: array<string, mixed>}
     */
    private function normalkanRespons(bool $httpSukses, int $kodeHttp, array $mentah, string $pesanDefault): array
    {
        $sukses = $httpSukses && (($mentah['success'] ?? false) === true);

        return [
            'sukses' => $sukses,
            'pesan' => (string) ($mentah['message'] ?? ($sukses ? 'Terkirim' : $pesanDefault)),
            'kode_http' => $kodeHttp,
            'mentah' => $mentah,
        ];
    }

    /**
     * @return array{sukses: bool, pesan: string, kode_http: int|null, mentah: array<string, mixed>}
     */
    private function hasilGagal(string $pesan, ?int $kodeHttp): array
    {
        return ['sukses' => false, 'pesan' => $pesan, 'kode_http' => $kodeHttp, 'mentah' => []];
    }

    /**
     * @return array<string, string>
     */
    private function header(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => (string) $this->setting->api_key,
        ];
    }

    private function url(string $path): string
    {
        return rtrim((string) $this->setting->base_url, '/').$path;
    }

    /**
     * StarSender menerima nomor berawalan 0 maupun berkode negara. Yang
     * dinormalkan hanya bentuk penulisannya: spasi, tanda hubung, dan tanda
     * kurung dibuang supaya nomor dari data donatur yang berbeda-beda formatnya
     * tidak gagal terkirim. Nomor yang tidak dikenali dikembalikan apa adanya —
     * penyedia yang menolaknya, dan galatnya tercatat di antrean.
     */
    private function normalkanNomor(string $nomor): string
    {
        $bersih = preg_replace('/[^0-9+]/', '', $nomor) ?? $nomor;

        if (str_starts_with($bersih, '+62')) {
            return '62'.substr($bersih, 3);
        }

        if (str_starts_with($bersih, '0')) {
            return '62'.substr($bersih, 1);
        }

        return $bersih;
    }
}
