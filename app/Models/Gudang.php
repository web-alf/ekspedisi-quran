<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gudang fisik tempat mushaf dikerjakan.
 *
 * Rombakan 2026-10-13 (Fase 2): ada dua gudang — Semarang dan Boyolali.
 * Manager Repacking (PJ) melihat informasi lengkap KEDUA gudang; di bawahnya
 * SPV Gudang per lokasi yang bisa assign kerjaan packing ke freelancer
 * warehouse. Penugasan tugas harian mewarisi gudang penugasa
 * (daily_packing_tasks.gudang_id), dan Fase 3 akan menautkan stok mushaf tanpa
 * muwakif ke gudang (transfer antar gudang pakai dokumen persetujuan).
 */
class Gudang extends Model
{
    protected $table = 'gudang';

    protected $fillable = ['kode', 'nama', 'deskripsi', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function dailyPackingTasks(): HasMany
    {
        return $this->hasMany(DailyPackingTask::class);
    }

    public static function byKode(string $kode): ?self
    {
        return static::where('kode', $kode)->first();
    }
}
