<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TargetPeriodeGudangFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Target mingguan/bulanan satu gudang dari Manager Gudang (30/10/2026).
 *
 * Progres tidak disimpan — dihitung TargetPeriodeService dari tugas harian freelancer.
 */
class TargetPeriodeGudang extends Model
{
    /** @use HasFactory<TargetPeriodeGudangFactory> */
    use HasFactory;

    public const TIPE_MINGGUAN = 'mingguan';

    public const TIPE_BULANAN = 'bulanan';

    public const KUNCI_SEMUA_JENIS = 'semua';

    protected $table = 'target_periode_gudang';

    protected $fillable = [
        'gudang_id',
        'tipe',
        'tanggal_mulai',
        'tanggal_selesai',
        'jenis_quran_id',
        'terjemahan',
        'kunci_jenis',
        'target',
        'catatan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'terjemahan' => 'boolean',
            'target' => 'integer',
        ];
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function jenisQuran(): BelongsTo
    {
        return $this->belongsTo(JenisQuran::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Rentang periode yang memuat tanggal acuan: Senin–Minggu untuk mingguan, tanggal 1
     * sampai akhir bulan untuk bulanan.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function rentang(string $tipe, \DateTimeInterface|string $acuan): array
    {
        $tanggal = CarbonImmutable::parse($acuan)->startOfDay();

        return $tipe === self::TIPE_MINGGUAN
            ? [$tanggal->startOfWeek(CarbonImmutable::MONDAY), $tanggal->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay()]
            : [$tanggal->startOfMonth(), $tanggal->endOfMonth()->startOfDay()];
    }

    /**
     * Periode yang belum berakhir (berjalan atau akan datang).
     *
     * @param  Builder<TargetPeriodeGudang>  $query
     * @return Builder<TargetPeriodeGudang>
     */
    public function scopeBelumBerakhir(Builder $query): Builder
    {
        return $query->whereDate('tanggal_selesai', '>=', today());
    }

    /** "November 2026" atau "9–15 Nov 2026". */
    public function labelPeriode(): string
    {
        $mulai = $this->tanggal_mulai->locale('id');
        $selesai = $this->tanggal_selesai->locale('id');

        if ($this->tipe === self::TIPE_BULANAN) {
            return $mulai->translatedFormat('F Y');
        }

        return $mulai->month === $selesai->month
            ? $mulai->format('j').'–'.$selesai->translatedFormat('j M Y')
            : $mulai->translatedFormat('j M').' – '.$selesai->translatedFormat('j M Y');
    }

    /** "A5 Terjemahan", atau null bila semua jenis. */
    public function labelJenis(): ?string
    {
        return $this->jenisQuran?->labelDenganVarian($this->terjemahan);
    }
}
