<?php

namespace App\Support;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cakupan data per gudang untuk peran SPV Gudang.
 *
 * Aturan pemilik sistem (21/10/2026): SPV hanya boleh melihat dan menyentuh data
 * gudangnya sendiri, PLUS data yang belum berlabel gudang. Yang belum berlabel bukan
 * milik gudang lain — ia belum ditentukan, dan hari ini justru hampir seluruh data
 * (26.100 resi). Menyembunyikannya akan membuat halaman SPV kosong dan memblokir
 * packing, padahal pemindaian itulah cara memberi labelnya.
 *
 * SPV yang BELUM DITEMPATKAN (gudang_id NULL) tetap dibatasi — hanya ke data yang belum
 * berlabel. Dulu akun seperti ini lolos dari pembatasan dan justru melihat kedua gudang
 * (25/10/2026).
 *
 * Peran lain (PJ/manager-repacking, super-admin) tidak dibatasi sama sekali.
 */
class CakupanGudang
{
    /** Apakah pengguna ini dibatasi cakupan gudang (semua SPV, ditempatkan atau belum). */
    public static function berlaku(?User $user): bool
    {
        return $user !== null && $user->hasRole(RoleEnum::SPV_GUDANG->value);
    }

    /** SPV yang belum ditempatkan di gudang mana pun. */
    public static function belumDitempatkan(?User $user): bool
    {
        return self::berlaku($user) && ! $user->gudang_id;
    }

    /**
     * Batasi query ke "gudang saya + belum berlabel".
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function terapkan(Builder $query, ?User $user, string $kolom = 'gudang_id'): Builder
    {
        if (! self::berlaku($user)) {
            return $query;
        }

        if (! $user->gudang_id) {
            return $query->whereNull($kolom);
        }

        return $query->where(function (Builder $q) use ($user, $kolom) {
            $q->where($kolom, $user->gudang_id)->orWhereNull($kolom);
        });
    }

    /** Boleh menyentuh (mengubah status) baris ini? */
    public static function bolehSentuh(?User $user, ?int $gudangBaris): bool
    {
        if (! self::berlaku($user)) {
            return true;
        }

        // Belum berlabel = boleh (barang belum diklaim gudang mana pun).
        return $gudangBaris === null
            || ($user->gudang_id && (int) $gudangBaris === (int) $user->gudang_id);
    }

    /** Pesan penolakan yang sama di semua jalur. */
    public static function pesanTolak(): string
    {
        return 'Resi ini tercatat di gudang lain. Hanya SPV gudang tersebut yang boleh mengubahnya.';
    }

    /** Pesan penolakan untuk kerdus — sama di semua jalur kerdus. */
    public static function pesanTolakKerdus(): string
    {
        return 'Kerdus ini tercatat di gudang lain. Hanya SPV gudang tersebut yang boleh mengubahnya.';
    }
}
