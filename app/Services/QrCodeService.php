<?php

namespace App\Services;

use App\Models\Pengiriman;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Pembuat QR resi — SATU sumber kebenaran.
 *
 * Sebelumnya logika ini hanya ada di dalam QRCodeController (private), sehingga
 * setiap kebutuhan lain (mis. backfill QR untuk resi lama lewat artisan) harus
 * menyalin ulang pengaturannya. Salinan seperti itu cepat menyimpang: hasil QR
 * dari tombol manual dan dari perintah artisan bisa berbeda, lalu pemindai
 * berperilaku tidak konsisten untuk barang yang sama.
 *
 * Isinya SENGAJA identik dengan perilaku lama — isi QR hanya nomor resi
 * (bukan JSON), format SVG, ukuran 300, margin 1, koreksi galat 'L'.
 */
class QrCodeService
{
    /**
     * Path pada disk default (`local`), yaitu storage/app/public/qr-codes.
     * Terbaca publik lewat symlink public/storage.
     */
    public const DIREKTORI = 'public/qr-codes';

    /**
     * Isi QR: nomor resi saja, seperti ekspedisi lain.
     *
     * Sengaja polos supaya pemindai gudang bisa memakainya langsung, dan supaya
     * berkasnya kecil (QR yang datanya panjang lebih sulit dipindai).
     */
    public function dataUntuk(Pengiriman $pengiriman): string
    {
        return $pengiriman->no_resi;
    }

    /**
     * Buat berkas QR dan kembalikan path-nya pada disk.
     *
     * @throws \Exception bila berkas gagal dibuat
     */
    public function buatUntuk(Pengiriman $pengiriman): string
    {
        if (! Storage::exists(self::DIREKTORI)) {
            Storage::makeDirectory(self::DIREKTORI);
        }

        $svg = QrCode::format('svg')
            ->size(300)
            ->margin(1)
            ->errorCorrection('L')
            ->generate($this->dataUntuk($pengiriman));

        // Nama berkas STABIL per resi (tanpa cap waktu).
        //
        // Versi lama memakai cap waktu detik "supaya unik", tetapi pembuatan
        // ulang dalam detik yang sama justru menghasilkan nama yang sama —
        // dan sebaliknya, membuat ulang di detik berbeda meninggalkan berkas
        // lama yang tidak dirujuk siapa pun (sampah yang menumpuk).
        // Nomor resi sudah unik per pengiriman, jadi nama ini sudah cukup unik.
        // Isinya deterministik (nomor resi), sehingga menimpa berkas lama aman.
        $path = self::DIREKTORI.'/QR-'.$pengiriman->no_resi.'.svg';

        Storage::put($path, $svg);

        if (! Storage::exists($path)) {
            throw new \Exception('Berkas QR tidak berhasil dibuat untuk resi '.$pengiriman->no_resi);
        }

        return $path;
    }

    /**
     * Apakah QR-nya sudah ada DAN berkasnya benar-benar masih ada di disk?
     *
     * Keduanya diperiksa: kolom `qr_code_path` bisa terisi sementara berkasnya
     * sudah hilang (mis. terhapus manual atau gagal saat pemulihan), dan resi
     * seperti itu akan tampak "sudah ada QR" padahal gudang tidak bisa
     * memindainya.
     */
    public function sudahAda(Pengiriman $pengiriman): bool
    {
        return $pengiriman->qr_code_path
            && Storage::exists($pengiriman->qr_code_path);
    }

    /**
     * Buat QR lalu simpan path & datanya ke resi. Mengembalikan path.
     */
    public function buatDanSimpan(Pengiriman $pengiriman): string
    {
        $path = $this->buatUntuk($pengiriman);

        $pengiriman->update([
            'qr_code_path' => $path,
            'qr_code_data' => $this->dataUntuk($pengiriman),
        ]);

        return $path;
    }
}
