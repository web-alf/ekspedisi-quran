<?php

namespace App\Enums;

use App\Models\JenisQuran;
use App\Models\Pengiriman;

/**
 * Ukuran label resi thermal, dipilih otomatis dari jenis mushaf (keputusan pemilik 28/10/2026).
 *
 * Resi ditempel di sampul tiap mushaf. Sampul A6 hanya 14 × 10 cm, jadi resi 10 × 15 cm
 * tidak muat; jenis di `pengiriman.jenis_label_resi_kecil` memakai label 9 × 13 cm.
 * Jenis lain (A5, Iqro) tetap 10 × 15 cm.
 */
enum UkuranLabelResi: string
{
    case Standar = '100x150';
    case Kecil = '90x130';

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

    public function label(): string
    {
        return match ($this) {
            self::Standar => '10 × 15 cm',
            self::Kecil => '9 × 13 cm',
        };
    }

    /**
     * Ukuran rol stiker yang harus terpasang di printer thermal.
     */
    public function rolStiker(): string
    {
        return match ($this) {
            self::Standar => '100 × 150 mm',
            self::Kecil => '90 × 130 mm',
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
