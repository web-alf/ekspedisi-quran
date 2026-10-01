<?php

namespace App\Support;

/**
 * Konversi satuan untuk isi kerdus.
 *
 * PENTING — arti satuan di lapangan (dikonfirmasi pemilik sistem):
 *
 *   1 doz = 1 kerdus penuh, dan isinya BERGANTUNG UKURAN Quran:
 *     - A5   : 20 eks per doz/kerdus
 *     - A6   : 40 eks per doz/kerdus
 *     - IQRO : 160 eks per doz/kerdus
 *
 * "Doz" di sini berarti dus/kerdus, BUKAN lusin. Kesalahan menafsirkannya
 * sebagai 12 keping membuat hitungan meleset jauh: kerdus A6 berisi 40 eks
 * akan terbaca "3,33 doz" seolah 40 keping, padahal itu tepat 1 doz.
 *
 * Karena itu satuan ini MURNI TURUNAN: pembaginya adalah kapasitas kerdus yang
 * memang dipakai (kolom `packing_boxes.kapasitas`, yang diisi dari
 * `jenis_quran.default_capacity`). Tidak ada tabel satuan dan tidak ada angka
 * yang diisi manual, sehingga tidak ada data baru yang bisa menyimpang.
 *
 * Rumus ini satu-satunya sumber angka satuan untuk seluruh aplikasi; jangan
 * menghitung ulang di controller/view lain.
 */
class BoxUnits
{
    /**
     * Satuan keping. Dipakai untuk menyebut jumlah eks/mushaf apa adanya.
     */
    public const PCS_LABEL = 'pcs';

    /**
     * Satuan kerdus. Di lapangan disebut "doz" (dus).
     */
    public const DOZ_LABEL = 'doz';

    /**
     * Rincian satuan dari sejumlah keping.
     *
     * @param  int  $pcs  jumlah keping
     * @param  int  $pcsPerDoz  isi 1 doz/kerdus untuk jenis ini (A5=20, A6=40, IQRO=160)
     * @return array{total: int, pcs: int, doz: float, pcs_per_doz: int, label: string}
     */
    public static function breakdown(int $pcs, int $pcsPerDoz): array
    {
        $pcs = max(0, $pcs);
        // Kapasitas tidak masuk akal (0/negatif) tidak boleh membuat pembagian
        // menghasilkan tak-hingga; perlakukan sebagai "tidak diketahui".
        $pcsPerDoz = $pcsPerDoz > 0 ? $pcsPerDoz : 0;

        $doz = $pcsPerDoz > 0 ? $pcs / $pcsPerDoz : 0.0;

        return [
            'total' => $pcs,
            'pcs' => $pcs,
            'doz' => round($doz, 2),
            'pcs_per_doz' => $pcsPerDoz,
            'label' => self::format($pcs, $doz, $pcsPerDoz),
        ];
    }

    /**
     * Label ringkas untuk jumlah keping.
     *
     * Aturan: sebut "doz" hanya bila kepingnya PAS kelipatan isi kerdus.
     * Keping yang belum penuh disebut apa adanya dalam pcs — 24 keping pada
     * kerdus A6 (isi 40) bukan "0,6 doz" yang membingungkan, tapi "24 pcs".
     *
     *   20 keping A5   -> "1 doz"      (kerdus penuh)
     *   40 keping A6   -> "1 doz"
     *   160 keping IQRO-> "1 doz"
     *   24 keping A5   -> "24 pcs"     (belum penuh, 6 keping lagi jadi 1 doz)
     *   30 keping A5   -> "30 pcs"
     */
    public static function format(int $pcs, float $doz, int $pcsPerDoz): string
    {
        if ($pcsPerDoz <= 0) {
            return "{$pcs} pcs";
        }

        // Pas penuh (atau beberapa kerdus penuh) baru disebut doz.
        if ($pcs > 0 && fmod((float) $pcs, (float) $pcsPerDoz) === 0.0) {
            return self::number($doz).' '.self::DOZ_LABEL;
        }

        return "{$pcs} ".self::PCS_LABEL;
    }

    /**
     * Keterangan konteks untuk ditampilkan di halaman, mis. "1 doz = 20 pcs (A5)".
     * Gudang menakar dalam doz, jadi pembacanya harus tahu isinya berapa.
     */
    public static function explanation(int $pcsPerDoz, ?string $kodeJenis = null): string
    {
        if ($pcsPerDoz <= 0) {
            return 'Kapasitas kerdus belum ditentukan';
        }

        $suffix = $kodeJenis ? " ({$kodeJenis})" : '';

        return '1 doz = '.$pcsPerDoz.' '.self::PCS_LABEL.$suffix;
    }

    /**
     * Angka gaya Indonesia: koma sebagai pemisah desimal, tanpa nol berekor.
     */
    private static function number(float $value): string
    {
        $formatted = number_format($value, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}
