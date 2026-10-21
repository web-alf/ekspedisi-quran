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
 * Peran lain (PJ/manager-repacking, super-admin) tidak dibatasi sama sekali.
 */
class CakupanGudang
{
    /** Apakah pengguna ini dibatasi ke satu gudang. */
    public static function berlaku(?User $user): bool
    {
        return $user !== null
            && $user->gudang_id
            && $user->hasRole(RoleEnum::SPV_GUDANG->value);
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
        return $gudangBaris === null || (int) $gudangBaris === (int) $user->gudang_id;
    }

    /** Pesan penolakan yang sama di semua jalur. */
    public static function pesanTolak(): string
    {
        return 'Resi ini tercatat di gudang lain. Hanya SPV gudang tersebut yang boleh mengubahnya.';
    }
}
