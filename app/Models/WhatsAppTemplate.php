<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    use HasFactory;

    /**
     * Ditulis eksplisit: Eloquent menebak "WhatsApp" sebagai dua kata dan
     * mencari tabel `whats_app_templates`.
     */
    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'name',
        'category',
        'title',
        'content',
        'variables',
        'is_active',
        'description',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Cari template berdasarkan kunci peristiwanya (`name`).
     */
    public static function untuk(string $eventKey): ?self
    {
        return static::query()->where('name', $eventKey)->first();
    }
}
