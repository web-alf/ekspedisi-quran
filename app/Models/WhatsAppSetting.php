<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class WhatsAppSetting extends Model
{
    use HasFactory;

    /**
     * Ditulis eksplisit: Eloquent menebak "WhatsApp" sebagai dua kata dan
     * mencari tabel `whats_app_settings`.
     */
    protected $table = 'whatsapp_settings';

    protected $fillable = [
        'provider',
        'api_key',
        'base_url',
        'sender_number',
        'is_active',
        'delay_seconds',
        'max_per_minute',
        'quiet_hours_start',
        'quiet_hours_end',
        'settings',
    ];

    protected $casts = [
        // Kunci API disimpan terenkripsi: nilainya tidak pernah muncul di dump
        // database, di log, atau di halaman pengaturan (hanya status terisi).
        'api_key' => 'encrypted',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    protected $hidden = [
        'api_key',
    ];

    /**
     * Baris pengaturan yang berlaku. Hanya ada satu; baris pertama dimenangkan.
     */
    public static function active(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    /**
     * Siap dipakai kirim? Butuh kunci API dan saklar aktif — dua-duanya.
     */
    public function siapKirim(): bool
    {
        return $this->is_active && filled($this->api_key);
    }

    /**
     * Sedang jam tenang? Rentang yang melewati tengah malam (mis. 22:00–06:00)
     * tetap dihitung benar, jadi tidak bisa dipakai untuk "membalik" artinya.
     */
    public function sedangJamTenang(?\DateTimeInterface $saat = null): bool
    {
        if (blank($this->quiet_hours_start) || blank($this->quiet_hours_end)) {
            return false;
        }

        $saat = $saat ? Carbon::instance($saat) : now();

        $mulai = Carbon::parse($saat->format('Y-m-d').' '.$this->quiet_hours_start);
        $selesai = Carbon::parse($saat->format('Y-m-d').' '.$this->quiet_hours_end);

        // Rentang normal, mis. 12:00–13:00.
        if ($mulai->lessThanOrEqualTo($selesai)) {
            return $saat->betweenIncluded($mulai, $selesai);
        }

        // Rentang melewati tengah malam, mis. 22:00–06:00: di luar jam itu
        // berarti masih jam tenang.
        return $saat->greaterThanOrEqualTo($mulai) || $saat->lessThanOrEqualTo($selesai);
    }
}
