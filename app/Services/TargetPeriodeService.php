<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\TargetPeriodeGudang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Progres target periode per gudang (30/10/2026).
 *
 * Progres = jumlah total_selesai tugas harian FREELANCER gudang itu di dalam rentang periode.
 * Tugas milik SPV dikecualikan: itu jatah dari Manager Gudang yang diteruskan ke freelancer,
 * sama seperti di Packing Hari Ini. Target yang dibatasi jenis hanya menghitung tugas berjenis
 * (dan bervarian) sama. Laju = target × hari berjalan ÷ jumlah hari periode (kalender).
 */
class TargetPeriodeService
{
    /**
     * Target yang belum berakhir (berjalan + akan datang), untuk tabel di Monitor & Penugasan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function belumBerakhir(?int $gudangId = null): array
    {
        return $this->ringkasSemua(
            TargetPeriodeGudang::query()
                ->belumBerakhir()
                ->when($gudangId !== null, fn (Builder $q) => $q->where('gudang_id', $gudangId))
                ->orderBy('tanggal_mulai')
                ->orderBy('gudang_id')
                ->orderBy('tipe')
                ->get()
        );
    }

    /**
     * Target yang sedang berjalan hari ini untuk satu gudang, untuk Dashboard Gudang.
     *
     * @return array<int, array<string, mixed>>
     */
    public function berjalan(int $gudangId): array
    {
        return $this->ringkasSemua(
            TargetPeriodeGudang::query()
                ->where('gudang_id', $gudangId)
                ->whereDate('tanggal_mulai', '<=', today())
                ->whereDate('tanggal_selesai', '>=', today())
                ->orderBy('tipe') // 'bulanan' < 'mingguan': bulanan tampil lebih dulu
                ->orderBy('kunci_jenis')
                ->get()
        );
    }

    /**
     * @param  Collection<int, TargetPeriodeGudang>  $daftar
     * @return array<int, array<string, mixed>>
     */
    private function ringkasSemua(Collection $daftar): array
    {
        $daftar->load(['gudang:id,nama', 'jenisQuran']);

        return $daftar->map(fn (TargetPeriodeGudang $t) => $this->ringkas($t))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function ringkas(TargetPeriodeGudang $target): array
    {
        $hariIni = today();
        $mulai = $target->tanggal_mulai->copy()->startOfDay();
        $selesai = $target->tanggal_selesai->copy()->startOfDay();
        $jumlahHari = (int) $mulai->diffInDays($selesai) + 1;

        $status = match (true) {
            $hariIni->lt($mulai) => 'akan_datang',
            $hariIni->gt($selesai) => 'selesai',
            default => 'berjalan',
        };

        $hariKe = match ($status) {
            'akan_datang' => 0,
            'selesai' => $jumlahHari,
            default => (int) $mulai->diffInDays($hariIni) + 1,
        };

        $dikemas = $status === 'akan_datang' ? 0 : $this->progres($target);
        $laju = (int) round($target->target * $hariKe / $jumlahHari);
        $sisa = max(0, $target->target - $dikemas);
        $sisaHari = $jumlahHari - $hariKe;

        return [
            'id' => $target->id,
            'gudang_id' => $target->gudang_id,
            'gudang' => $target->gudang?->nama,
            'tipe' => $target->tipe,
            'periode' => $target->labelPeriode(),
            'tanggal_mulai' => $target->tanggal_mulai->toDateString(),
            'tanggal_selesai' => $target->tanggal_selesai->toDateString(),
            'jenis' => $target->labelJenis(),
            'jenis_nilai' => $target->kunci_jenis === TargetPeriodeGudang::KUNCI_SEMUA_JENIS ? null : $target->kunci_jenis,
            'target' => $target->target,
            'catatan' => $target->catatan,
            'status' => $status,
            'dikemas' => $dikemas,
            'persen' => $target->target > 0 ? min(100, (int) round($dikemas / $target->target * 100)) : 0,
            'hari_ke' => $hariKe,
            'jumlah_hari' => $jumlahHari,
            'laju' => $laju,
            // Positif = di depan laju, negatif = tertinggal.
            'selisih_laju' => $dikemas - $laju,
            'sisa' => $sisa,
            'sisa_hari' => $sisaHari,
            // Tanpa sisa hari (hari terakhir), sisa harus selesai hari ini.
            'perlu_per_hari' => $sisa === 0 ? 0 : (int) ceil($sisa / max(1, $sisaHari)),
        ];
    }

    public function progres(TargetPeriodeGudang $target): int
    {
        return (int) DailyPackingTask::query()
            ->where('gudang_id', $target->gudang_id)
            ->whereDate('tanggal_tugas', '>=', $target->tanggal_mulai)
            ->whereDate('tanggal_tugas', '<=', $target->tanggal_selesai)
            ->whereDoesntHave('user.roles', fn (Builder $roles) => $roles->where('name', RoleEnum::SPV_GUDANG->value))
            ->when($target->jenis_quran_id !== null, fn (Builder $q) => $q
                ->where('jenis_quran_id', $target->jenis_quran_id)
                ->when($target->terjemahan !== null, fn (Builder $v) => $v->where('terjemahan', $target->terjemahan)))
            ->sum('total_selesai');
    }
}
