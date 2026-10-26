<?php

namespace App\Support;

use App\Enums\RoleEnum;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Kelompok pilihan role untuk form Tambah/Ubah Pengguna.
 *
 * Daftar `Role::all()` mentah membingungkan pemilik sistem (26/10/2026): urutannya
 * urutan id di basis data, `warehouse` berlabel "Staff Gudang" padahal peran itu
 * adalah FREELANCER, dan role `distribusi` yang tidak punya akun sama sekali ikut
 * ditawarkan. Kelas ini menyusun ulang daftarnya: tiga kelompok yang mengikuti alur
 * kerja (gudang, distribusi, kantor), urutan tetap, dan role yang tidak dipakai lagi
 * disembunyikan.
 *
 * Label diambil dari `roles.display_name` (sumber tunggal, dipakai juga oleh daftar
 * pengguna dan filternya) — jangan menyalin label di sini, cukup atur pengelompokan.
 */
class PilihanRole
{
    /**
     * Role yang tidak ditawarkan lagi di form pengguna.
     *
     * `distribusi` tidak lagi dipakai alur mana pun dan tidak punya akun; menawarkannya
     * hanya menambah pilihan yang salah. Baris role-nya tetap ada (tidak dihapus) supaya
     * akun lama yang mungkin memegangnya masih terbaca.
     */
    public const DISEMBUNYIKAN = [
        RoleEnum::DISTRIBUSI->value,
    ];

    /**
     * Susunan kelompok untuk form. Kunci = judul kelompok, nilai = urutan role.
     *
     * @var array<string, array<int, string>>
     */
    private const KELOMPOK = [
        'Gudang' => [
            RoleEnum::WAREHOUSE->value,
            RoleEnum::SPV_GUDANG->value,
            RoleEnum::MANAGER_REPACKING->value,
        ],
        'Distribusi & Pengiriman' => [
            RoleEnum::MANAGER->value,
            RoleEnum::SUPERVISOR->value,
            RoleEnum::COURIER->value,
        ],
        'Kantor & Sistem' => [
            RoleEnum::CUSTOMER_SERVICE->value,
            RoleEnum::SUPER_ADMIN->value,
        ],
    ];

    /**
     * Kelompok role siap dirender sebagai <optgroup>.
     *
     * @param  string|null  $roleSaatIni  Role yang sedang dipegang akun yang diedit —
     *                                    selalu ikut ditampilkan (kelompok "Lainnya") meski
     *                                    tersembunyi dari form, supaya akun lama tidak
     *                                    terlihat tidak punya role.
     * @return array<int, array{label: string, roles: array<int, array{value: string, label: string}>}>
     */
    public static function kelompok(?string $roleSaatIni = null): array
    {
        $label = self::labelPeran();
        $kelompok = [];
        $sudahTampil = [];

        foreach (self::KELOMPOK as $judul => $namaPeran) {
            $roles = [];

            foreach ($namaPeran as $nama) {
                if (! isset($label[$nama]) || in_array($nama, self::DISEMBUNYIKAN, true)) {
                    continue; // role belum ada di basis data ini, atau sengaja disembunyikan
                }

                $roles[] = ['value' => $nama, 'label' => $label[$nama]];
                $sudahTampil[] = $nama;
            }

            if ($roles !== []) {
                $kelompok[] = ['label' => $judul, 'roles' => $roles];
            }
        }

        if ($roleSaatIni !== null && ! in_array($roleSaatIni, $sudahTampil, true) && isset($label[$roleSaatIni])) {
            $kelompok[] = [
                'label' => 'Lainnya',
                'roles' => [['value' => $roleSaatIni, 'label' => $label[$roleSaatIni]]],
            ];
        }

        return $kelompok;
    }

    /**
     * Label tiap role: display_name bila ada, kalau tidak nama teknis yang dirapikan.
     *
     * Role yang disembunyikan tetap disertakan di sini — akun lama yang memegangnya
     * masih perlu labelnya saat diedit.
     *
     * @return array<string, string>
     */
    private static function labelPeran(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->get(['name', 'display_name'])
            ->mapWithKeys(fn (Role $role) => [
                $role->name => $role->display_name ?: Str::headline($role->name),
            ])
            ->all();
    }
}
