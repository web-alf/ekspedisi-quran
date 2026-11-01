<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WakafItem extends Model
{
    use HasFactory;

    /**
     * Panjang doa yang muat di label resi 100×150 mm tanpa mendorong isi keluar label
     * (keputusan pemilik 28/10/2026). Dipakai validasi input dan pemotongan di label;
     * doa panjang dicetak dengan font lebih kecil (lihat kelasUkuranDoaLabel()).
     */
    public const DOA_MAKS_KARAKTER = 250;

    /**
     * Doa untuk dicetak di label: pilihan doa standar dari halaman website dibuang
     * (lihat buangOpsiDoaWebsite()), lalu doa lama yang lebih panjang dipotong dengan "…".
     */
    public static function doaUntukLabel(?string $doa): ?string
    {
        $doa = $doa !== null ? self::buangOpsiDoaWebsite($doa) : null;

        if ($doa === null || $doa === '') {
            return null;
        }

        if (mb_strlen($doa) <= self::DOA_MAKS_KARAKTER) {
            return $doa;
        }

        return Str::limit($doa, self::DOA_MAKS_KARAKTER - 1, '…', preserveWords: true);
    }

    /**
     * Doa untuk lembar wakaf (resi kertas A5/A6): SAMA dengan yang tersimpan di database —
     * tidak dipotong, baris barunya dipertahankan (keputusan pemilik 30/10/2026). Hanya
     * pilihan doa standar dari halaman website yang dibuang (buangOpsiDoaWebsite()), dan
     * "\n" yang tersimpan sebagai teks (sisa impor) dibaca sebagai baris baru.
     */
    public static function doaUntukLembar(?string $doa): ?string
    {
        if ($doa === null) {
            return null;
        }

        $doa = self::buangOpsiDoaWebsite(str_replace(['\\r\\n', '\\n', '\\r'], "\n", $doa));

        return $doa === '' ? null : $doa;
    }

    /**
     * Buang bagian "DOA MUWAKIF YANG MASIH HIDUP" / "DOA MUWAKIF YANG SUDAH MENINGGAL"
     * beserta doa standarnya dari teks doa.
     *
     * Kedua bagian itu pilihan doa di halaman website (keputusan pemilik 30/10/2026: tidak
     * dicetak), tetapi pada data impor Des 2025 – Jun 2026 ikut tersalin utuh ke doa_request
     * di depan doa pribadi. Yang tersisa hanya doa pribadi; penyambung di awal ("&", "dan")
     * ikut dibuang. Data asli tidak diubah.
     */
    public static function buangOpsiDoaWebsite(string $doa): string
    {
        // Hanya teks yang memuat judul pilihan atau doa standarnya yang diproses; doa biasa
        // (termasuk yang menyebut kata "muwakif") dikembalikan apa adanya.
        if (! preg_match('/DOA\s+MUWAKIF\s+YANG\s+(?:MASIH\s+HIDUP|SUDAH\s+MENINGGAL)|limpahkan rahmat-Mu kepada muwakif dan keluarganya|ampunilah dosa-dosa almarhum\/ah/iu', $doa)) {
            return trim($doa);
        }

        $teks = str_replace(['\\r\\n', '\\n', '\\r'], ' ', $doa);
        $teks = str_replace(['“', '”', '„'], '"', $teks);

        // Judul pilihan + doa standar dalam tanda kutip (bila ada).
        $teks = preg_replace('/DOA\s+MUWAKIF\s+YANG\s+(?:MASIH\s+HIDUP|SUDAH\s+MENINGGAL)\s*:?\s*(?:"[^"]*"?)?/iu', ' ', $teks);
        // Doa standar yang tertinggal tanpa judul atau tanpa tanda kutip.
        $teks = preg_replace('/Ya Allah, limpahkan rahmat-Mu kepada muwakif dan keluarganya\..*?Rabbal [\'’]aalamiin\.?"?/isu', ' ', $teks);
        $teks = preg_replace('/Ya Allah, ampunilah dosa-dosa almarhum\/ah,.*?Rabbal [\'’]aalamiin\.?"?/isu', ' ', $teks);

        $teks = trim(preg_replace('/[ \t]+/u', ' ', preg_replace('/\s*\R\s*/u', "\n", $teks)));
        $teks = preg_replace('/^(?:(?:&|\+|-|,|\.|\||dan\b)\s*)+/iu', '', $teks);

        return trim($teks, " \n\"'");
    }

    /**
     * Kelas ukuran font doa di label resi, bertingkat menurut panjang doa yang dicetak:
     * sampai 100 karakter 7pt (2 baris), sampai 180 karakter 6,5pt (3 baris),
     * selebihnya 6pt (4 baris). Ditentukan di server agar cetak satuan dan massal sama.
     */
    public static function kelasUkuranDoaLabel(?string $doa): string
    {
        $panjang = mb_strlen((string) self::doaUntukLabel($doa));

        return match (true) {
            $panjang <= 100 => 'doa-normal',
            $panjang <= 180 => 'doa-sedang',
            default => 'doa-panjang',
        };
    }

    protected $fillable = [
        'donatur_id',
        'pengiriman_id',
        'wakaf_type',
        'sequence_in_type',
        'global_sequence',
        'wakif_name',
        'doa_request',
        'relationship_to_donatur',
        'status',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'sequence_in_type' => 'integer',
        'global_sequence' => 'integer',
    ];

    // Relationships
    public function donatur(): BelongsTo
    {
        return $this->belongsTo(Donatur::class);
    }

    public function pengiriman(): BelongsTo
    {
        return $this->belongsTo(Pengiriman::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Accessors
    public function getWakafTypeNameAttribute(): string
    {
        return match ($this->wakaf_type) {
            'A5' => 'Mushaf Al-Qur\'an A5',
            'A6' => 'Mushaf Al-Qur\'an A6',
            'IQRA' => 'IQRA',
            default => $this->wakaf_type
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Menunggu Proses',
            'processed' => 'Sudah Diproses',
            'shipped' => 'Dikirim',
            'delivered' => 'Diterima',
            default => $this->status
        };
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->wakaf_type_name} #{$this->global_sequence} - {$this->wakif_name}";
    }

    // Scopes
    public function scopeByDonatur($query, $donaturId)
    {
        return $query->where('donatur_id', $donaturId);
    }

    public function scopeByWakafType($query, $type)
    {
        return $query->where('wakaf_type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessed($query)
    {
        return $query->whereIn('status', ['processed', 'shipped', 'delivered']);
    }

    // Helper methods
    public function canBeEdited(): bool
    {
        return in_array($this->status, ['pending']);
    }

    public function canBeDeleted(): bool
    {
        // Item must be pending
        if ($this->status !== 'pending') {
            return false;
        }

        // If pengiriman exists, check its status
        if ($this->pengiriman) {
            $pengirimanStatus = $this->pengiriman->status->nama ?? 'Unknown';
            $deletableStatuses = ['Pending', 'Proses Pemesanan', 'Batal'];

            return in_array($pengirimanStatus, $deletableStatuses);
        }

        // If no pengiriman, it can be deleted
        return true;
    }

    public function markAsProcessed(): void
    {
        $this->update(['status' => 'processed']);
    }

    public function markAsShipped(): void
    {
        $this->update(['status' => 'shipped']);
    }

    public function markAsDelivered(): void
    {
        $this->update(['status' => 'delivered']);
    }
}
