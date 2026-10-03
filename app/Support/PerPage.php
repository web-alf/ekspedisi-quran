<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Ukuran halaman (baris per halaman) yang seragam untuk seluruh tabel admin.
 *
 * Dulu tiap controller mematok angkanya sendiri-sendiri (10, 12, 15, 20, 25,
 * 100, sampai 500), sehingga perilakunya berbeda-beda di tiap halaman dan
 * pengguna tidak punya cara memilih. Semua tabel kini memakai daftar yang sama
 * dengan bawaan 20.
 *
 * Nilai dari permintaan SELALU dipaksa cocok dengan daftar ini: tanpa itu,
 * ?per_page=999999 bisa menarik seluruh tabel sekaligus dan menggantungkan
 * server.
 */
final class PerPage
{
    /** @var array<int, int> */
    public const OPTIONS = [10, 20, 50, 100, 200];

    public const DEFAULT = 20;

    /**
     * Ambil ukuran halaman yang sah dari permintaan, atau bawaan bila tidak sah.
     */
    public static function resolve(?Request $request = null, ?int $default = null): int
    {
        $default ??= self::DEFAULT;

        if (! $request) {
            return $default;
        }

        $nilai = (int) $request->input('per_page', $default);

        return in_array($nilai, self::OPTIONS, true) ? $nilai : $default;
    }

    /**
     * Props yang dikirim ke frontend supaya pemilihnya bisa dirender.
     *
     * @return array{perPage: int, perPageOptions: array<int, int>}
     */
    public static function props(?Request $request = null, ?int $default = null): array
    {
        return [
            'perPage' => self::resolve($request, $default),
            'perPageOptions' => self::OPTIONS,
        ];
    }
}
