<?php

namespace App\Support;

/**
 * Konversi satuan untuk isi kerdus.
 *
 * Kebutuhan lapangan menyebut satuan pcs / doz. Satuan ini MURNI TURUNAN dari
 * jumlah keping per kerdus — tidak ada tabel satuan dan tidak ada angka yang
 * perlu diisi manual, sehingga tidak ada data baru yang bisa menyimpang.
 *
 * Catatan: "lusin" SENGAJA TIDAK ditampilkan sebagai kolom tersendiri karena
 * nilainya identik dengan doz (satuannya sama-sama 12 keping). Dua kolom yang
 * selalu berisi angka yang sama hanya membingungkan pembaca. Bila di lapangan
 * lusin ternyata dihitung dengan kelipatan berbeda, ubah PCS_PER_LUSIN di sini.
 *
 * Rumus ini satu-satunya sumber angka satuan untuk seluruh aplikasi; jangan
 * menghitung ulang di controller/view lain.
 */
class BoxUnits
{
    public const PCS_PER_DOZ = 12;

    public const PCS_PER_LUSIN = 12;

    /**
     * Rincian satuan dari sejumlah keping.
     *
     * @return array{total: int, pcs: int, doz: float, lusin: float, label: string}
     */
    public static function breakdown(int $pcs): array
    {
        $pcs = max(0, $pcs);

        $doz = $pcs / self::PCS_PER_DOZ;

        return [
            'total' => $pcs,
            'pcs' => $pcs,
            'doz' => round($doz, 2),
            'lusin' => round($pcs / self::PCS_PER_LUSIN, 2),
            'label' => self::format($pcs, $doz),
        ];
    }

    /**
     * Label ringkas: pakai doz hanya bila kepingnya memang pas kelipatan 12,
     * selain itu tampilkan keping apa adanya.
     *
     * 24 keping → "2 doz", 30 keping → "2,5 doz", 20 keping → "20 pcs".
     * Keping yang tidak pas TIDAK dibulatkan ke bawah: 20 keping bukan "1 doz",
     * dan menampilkannya begitu membuat isi kerdus tampak berkurang.
     */
    public static function format(int $pcs, float $doz): string
    {
        if ($pcs < self::PCS_PER_DOZ) {
            return "{$pcs} pcs";
        }

        if (fmod($doz, 0.5) === 0.0) {
            return self::number($doz).' doz';
        }

        return "{$pcs} pcs";
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
