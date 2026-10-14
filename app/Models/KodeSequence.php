<?php

namespace App\Models;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Nomor urut atomik per kunci untuk kode berformat "PREFIX-00001".
 *
 * Pengganti pola "ambil nomor terakhir lalu +1" yang tidak aman terhadap
 * request bersamaan. Baris kunci dibuat sekali (insertOrIgnore, jadi dua
 * pembuat pertama tidak saling menggagalkan) dengan nilai awal dari data yang
 * sudah ada, lalu setiap pengambilan nomor mengunci baris itu.
 */
class KodeSequence extends Model
{
    protected $fillable = [
        'kunci',
        'last_number',
    ];

    /**
     * Ambil nomor berikutnya untuk kunci ini.
     *
     * @param  Closure(): int  $nomorTerakhirSaatIni  Dipanggil hanya saat kunci
     *                                                belum pernah dipakai, untuk melanjutkan dari data lama.
     */
    public static function berikutnya(string $kunci, Closure $nomorTerakhirSaatIni): int
    {
        return DB::transaction(function () use ($kunci, $nomorTerakhirSaatIni) {
            $baris = static::where('kunci', $kunci)->lockForUpdate()->first();

            if (! $baris) {
                static::query()->insertOrIgnore([
                    'kunci' => $kunci,
                    'last_number' => max(0, (int) $nomorTerakhirSaatIni()),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $baris = static::where('kunci', $kunci)->lockForUpdate()->firstOrFail();
            }

            $baris->increment('last_number');

            return (int) $baris->last_number;
        }, 3);
    }
}
