<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppNotification extends Model
{
    use HasFactory;

    /**
     * Ditulis eksplisit: Eloquent menebak "WhatsApp" sebagai dua kata dan
     * mencari tabel `whats_app_notifications`.
     */
    protected $table = 'whatsapp_notifications';

    protected $fillable = [
        'event_key',
        'dedupe_key',
        'template_id',
        'donatur_id',
        'pengiriman_id',
        'wakaf_batch_id',
        'recipient',
        'recipient_name',
        'body',
        'status',
        'attempts',
        'provider_message_id',
        'provider_response',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'provider_response' => 'array',
        'attempts' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'template_id');
    }

    public function donatur(): BelongsTo
    {
        return $this->belongsTo(Donatur::class);
    }

    public function pengiriman(): BelongsTo
    {
        return $this->belongsTo(Pengiriman::class);
    }

    public function wakafBatch(): BelongsTo
    {
        return $this->belongsTo(WakafBatch::class, 'wakaf_batch_id');
    }

    public function scopeMenunggu(Builder $query): Builder
    {
        return $query->where('status', 'menunggu');
    }

    public function scopeGagal(Builder $query): Builder
    {
        return $query->where('status', 'gagal');
    }

    public function scopeTerkirim(Builder $query): Builder
    {
        return $query->where('status', 'terkirim');
    }

    public function sudahSelesai(): bool
    {
        return $this->status === 'terkirim';
    }

    /**
     * Catat hasil kirim. Dipanggil dari job, jadi sengaja menerima data mentah
     * dari penyedia apa adanya supaya bisa diperiksa ulang saat ada keluhan.
     *
     * @param  array<string, mixed>  $respons
     */
    public function tandaiTerkirim(array $respons): void
    {
        $this->update([
            'status' => 'terkirim',
            'attempts' => $this->attempts + 1,
            'provider_message_id' => data_get($respons, 'data.id') ?? data_get($respons, 'id'),
            'provider_response' => $respons,
            'error_message' => null,
            'sent_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $respons
     */
    public function tandaiGagal(string $pesan, array $respons = []): void
    {
        $this->update([
            'status' => 'gagal',
            'attempts' => $this->attempts + 1,
            'provider_response' => $respons ?: null,
            'error_message' => $pesan,
        ]);
    }
}
