<?php

namespace App\Models;

use App\Services\SerahTerimaKurirService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu serah terima resi oleh kurir: di tujuan, atau dialihkan di tengah jalan.
 *
 * @see SerahTerimaKurirService
 */
class SerahTerimaKurir extends Model
{
    /** Diserahkan di lokasi penerima yang tertulis di resi. */
    public const JENIS_TUJUAN = 'tujuan';

    /** Diminta orang lain dalam perjalanan; resi dialihkan ke penerima baru. */
    public const JENIS_TENGAH_JALAN = 'tengah_jalan';

    protected $table = 'serah_terima_kurir';

    protected $fillable = [
        'pengiriman_id',
        'muatan_id',
        'user_id',
        'jenis',
        'kode_kerdus',
        'nama_penerima',
        'no_hp_penerima',
        'alamat_penerima',
        'latitude',
        'longitude',
        'foto',
        'catatan',
        'mushaf_request_id_asal',
        'nama_lembaga_asal',
        'nama_penerima_asal',
        'alamat_asal',
    ];

    protected function casts(): array
    {
        return [
            'foto' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function pengiriman(): BelongsTo
    {
        return $this->belongsTo(Pengiriman::class);
    }

    public function muatan(): BelongsTo
    {
        return $this->belongsTo(Muatan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mushafRequestAsal(): BelongsTo
    {
        return $this->belongsTo(MushafRequest::class, 'mushaf_request_id_asal');
    }
}
