<?php

namespace App\Enums;

use App\Models\JenisQuran;
use App\Models\Pengiriman;

/**
 * Ukuran label resi, dipilih otomatis dari jenis mushaf.
 *
 * Resi dicetak printer biasa di kertas yang sudah dipotong A5 (148 × 210 mm) atau
 * A6 (105 × 148 mm), satu label per lembar, sisa 10 mm tiap sisi (keputusan pemilik
 * 29/10/2026; sebelumnya label thermal 10 × 15 cm dan 9 × 13 cm yang terlalu kecil).
 * Jenis di `pengiriman.jenis_label_resi_kecil` (A6, sampul 14 × 10 cm) memakai kertas A6;
 * jenis lain (A5, Iqro) memakai kertas A5.
 */
enum UkuranLabelResi: string
{
    case Standar = 'a5';
    case Kecil = 'a6';

    public static function untukJenis(?JenisQuran $jenisQuran): self
    {
        $kodeJenis = strtoupper((string) $jenisQuran?->kode_jenis);

        return in_array($kodeJenis, config('pengiriman.jenis_label_resi_kecil', []), true)
            ? self::Kecil
            : self::Standar;
    }

    public static function untukPengiriman(Pengiriman $pengiriman): self
    {
        return self::untukJenis($pengiriman->jenisQuran);
    }

    /**
     * Nama ukuran yang tampil di halaman, mis. "A5 (12,8 × 19 cm)".
     */
    public function label(): string
    {
        return match ($this) {
            self::Standar => 'A5 (12,8 × 19 cm)',
            self::Kecil => 'A6 (8,5 × 12,8 cm)',
        };
    }

    /**
     * Kertas yang harus dimasukkan ke printer.
     */
    public function kertas(): string
    {
        return match ($this) {
            self::Standar => 'A5 (148 × 210 mm)',
            self::Kecil => 'A6 (105 × 148 mm)',
        };
    }

    public function templateSatuan(): string
    {
        return 'admin.thermal-print.label-'.$this->value;
    }

    public function templateMassal(): string
    {
        return 'admin.thermal-print.bulk-'.$this->value;
    }
}
