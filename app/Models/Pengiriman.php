<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class Pengiriman extends Model
{
    use HasFactory;

    protected $table = 'pengiriman';

    protected $fillable = [
        'no_resi',
        'donatur_id',
        // Tanpa baris ini, `Pengiriman::create([...])` dan `$pengiriman->update([...])`
        // MEMBUANG `mushaf_request_id` diam-diam — mass assignment tidak melempar
        // galat, nilainya cuma hilang. Dua jalur lain lolos dari jebakan ini karena
        // memakai query builder (`Pengiriman::whereIn(...)->update([...])`) yang
        // melewati penjagaan fillable; jalur `processToShipment` memakai model,
        // sehingga kiriman hasil halaman permintaan tidak pernah tertaut balik.
        'mushaf_request_id',
        'wakaf_batch_id',
        'sequence_in_batch',
        'donation_id',
        'wakaf_item_id',
        'jenis_quran_id',
        // Lokasi fisik mushaf ini (LOKASI MUSHAF, 2026-10-15). Tanpa baris fillable ini,
        // `$pengiriman->update(['gudang_id' => ...])` MEMBUANG nilainya diam-diam.
        'gudang_id',
        'jumlah_quran',
        'tanggal_wakaf',
        'status_id',
        'qr_code_path',
        'qr_code_data',
        'alamat_tujuan',
        'nama_penerima',
        'nama_lembaga',
        'no_hp_penerima',
        'catatan',
        'received_at',
        'received_by',
        'receiver_contact',
        'delivery_proof',
        'delivery_notes',
        'sertifikat_generated',
        'sertifikat_path',
        'created_by',
    ];

    protected $casts = [
        'tanggal_wakaf' => 'date',
        'received_at' => 'datetime',
        'delivery_proof' => 'array',
        'sertifikat_generated' => 'boolean',
    ];

    /**
     * 🚀 AUTO-GENERATE NO_RESI
     * Boot method untuk auto-generate no_resi saat create
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Auto generate no_resi jika belum ada
            if (empty($model->no_resi)) {
                $model->no_resi = $model->generateUniqueNoResi();
            }

            $model->tautkanKeBatchDonatur();
        });

        // "Diterima" hanya dari "Tiba di Tujuan" (keputusan pemilik sistem, 16/10/2026):
        // kurir menyerahkan di lokasi dulu (nama penerima, foto, GPS), baru manager/
        // distribusi menyatakan diterima. Dijaga di model — bukan di tiap controller —
        // karena ada setidaknya tujuh jalur yang bisa mengubah status (form edit, ubah
        // massal, pindai status, tracking, muatan, kerdus massal, perintah artisan);
        // menjaganya satu per satu berarti satu jalur yang terlewat membuka aturannya.
        static::updating(function (Pengiriman $model) {
            if ($alasan = $model->alasanTidakBolehMenjadiDiterima()) {
                throw new \DomainException($alasan);
            }
        });
    }

    /**
     * Alasan perubahan status yang sedang disimpan TIDAK boleh menjadi "Diterima", atau
     * null bila boleh (atau statusnya memang tidak sedang berubah ke "Diterima").
     */
    public function alasanTidakBolehMenjadiDiterima(): ?string
    {
        if (! $this->isDirty('status_id')) {
            return null;
        }

        $slugBaru = StatusPengiriman::query()->whereKey($this->status_id)->value('slug');

        if ($slugBaru !== 'diterima') {
            return null;
        }

        $slugLama = StatusPengiriman::query()->whereKey($this->getOriginal('status_id'))->value('slug');

        if ($slugLama === self::SLUG_TIBA) {
            return null;
        }

        return "Resi {$this->no_resi} belum \"Tiba di Tujuan\". Kurir harus memindai serah terima di lokasi dulu sebelum resi bisa dinyatakan Diterima.";
    }

    /**
     * Isi wakaf_batch_id dari batch milik donatur bila belum diisi.
     *
     * Kenapa di sini: dari lima jalur pembuatan resi, hanya satu yang mengisi
     * wakaf_batch_id (DonaturController::storeBatch). Jalur lain — impor donatur
     * massal, permintaan mushaf, dan penambahan item wakaf — membiarkannya null.
     * Akibatnya relasi `WakafBatch::pengiriman()` selalu kosong, dan
     * WakafBatch::updateStatus() karena itu selalu menyimpulkan "belum ada
     * pengiriman" lalu menulis ulang status ke pending_distribution. Di produksi
     * ini membuat 15.552 batch menggantung: 0 dari 26.111 resi punya wakaf_batch_id.
     *
     * Hanya ditautkan bila donaturnya punya TEPAT SATU batch — kalau lebih dari
     * satu, pilihannya ambigu dan menebak bisa menautkan resi ke batch yang salah.
     */
    protected function tautkanKeBatchDonatur(): void
    {
        if (! empty($this->wakaf_batch_id) || empty($this->donatur_id)) {
            return;
        }

        $idBatch = WakafBatch::query()
            ->where('donatur_id', $this->donatur_id)
            ->limit(2)
            ->pluck('id');

        if ($idBatch->count() === 1) {
            $this->wakaf_batch_id = $idBatch->first();
        }
    }

    /**
     * Generate nomor resi unik dengan format EQ-YYYY-XXXXX
     *
     * ✅ Race-condition safe menggunakan database sequence
     * ✅ Atomic increment dengan lockForUpdate
     * ✅ No collision possible
     */
    public function generateUniqueNoResi()
    {
        $tahun = (int) date('Y');

        // Get next number dari sequence table (atomic & race-safe)
        $nextNumber = NoResiSequence::getNextNumber($tahun);

        // Format: EQ-YYYY-XXXXX
        return "EQ-{$tahun}-".str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @deprecated Use generateUniqueNoResi() instead
     * Kept for backward compatibility, but now uses new sequence system
     */
    private function generateNoResi()
    {
        return $this->generateUniqueNoResi();
    }

    /**
     * Relationship: Pengiriman belongs to Donatur
     */
    public function donatur()
    {
        return $this->belongsTo(Donatur::class, 'donatur_id');
    }

    /**
     * Relationship: Pengiriman belongs to WakafBatch
     */
    public function wakafBatch()
    {
        return $this->belongsTo(WakafBatch::class, 'wakaf_batch_id');
    }

    /**
     * Relationship: Pengiriman belongs to JenisQuran
     */
    public function jenisQuran()
    {
        return $this->belongsTo(JenisQuran::class, 'jenis_quran_id');
    }

    /**
     * Relationship: Pengiriman belongs to StatusPengiriman
     */
    public function status()
    {
        return $this->belongsTo(StatusPengiriman::class, 'status_id');
    }

    /**
     * Relationship: Pengiriman belongs to Donation
     */
    public function donation()
    {
        return $this->belongsTo(Donation::class);
    }

    /**
     * Relationship: Pengiriman belongs to WakafItem
     */
    public function wakafItem()
    {
        return $this->belongsTo(WakafItem::class);
    }

    /**
     * Relationship: Pengiriman belongs to User (creator)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 🆕 Relationship: Pengiriman has many StatusHistory
     */
    public function statusHistory()
    {
        return $this->hasMany(StatusHistory::class, 'pengiriman_id');
    }

    /**
     * Relationship: Pengiriman has many TrackingHistory
     */
    public function trackingHistory()
    {
        return $this->hasMany(TrackingHistory::class);
    }

    /**
     * 🆕 Relationship: Pengiriman has one Sertifikat
     */
    public function sertifikat()
    {
        return $this->hasOne(Sertifikat::class, 'pengiriman_id');
    }

    /**
     * Relationship: Pengiriman has one MushafRequest (reverse lookup)
     */
    public function mushafRequest()
    {
        return $this->hasOne(MushafRequest::class, 'pengiriman_id');
    }

    /**
     * 📦 Relationship: Pengiriman has one DailyPackingTaskItem
     */
    public function dailyPackingTaskItem()
    {
        return $this->hasOne(DailyPackingTaskItem::class);
    }

    /**
     * 📦 Relationship: Pengiriman has one PackingItem
     */
    public function packingItem()
    {
        return $this->hasOne(PackingItem::class);
    }

    /** Status yang boleh dimuat ke kurir: sudah selesai packing atau sedang diantar. */
    public const SLUG_SIAP_DIMUAT = ['selesai-packing', 'pengiriman'];

    /** Kurir sudah menyerahkan di lokasi; menunggu konfirmasi "Diterima". */
    public const SLUG_TIBA = 'tiba';

    /** Status yang boleh diserahterimakan kurir: barangnya sedang dibawa. */
    public const SLUG_BOLEH_DISERAHKAN = ['selesai-packing', 'pengiriman'];

    /** Jejak serah terima kurir untuk resi ini (terbaru di atas). */
    public function serahTerimaKurir(): HasMany
    {
        return $this->hasMany(SerahTerimaKurir::class)->latest('id');
    }

    /**
     * Gudang tempat mushaf ini berada (LOKASI MUSHAF, 2026-10-15).
     *
     * Diisi otomatis: dari kerdus saat mushaf masuk kerdus, atau dari gudang pelaku saat
     * resinya bergerak di tahap gudang. Sesudah itu hanya dokumen perpindahan
     * (`transfer_mushaf`) yang mengubahnya.
     */
    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    /** Baris dokumen perpindahan yang memuat mushaf ini. */
    public function transferMushafItems(): HasMany
    {
        return $this->hasMany(TransferMushafItem::class);
    }

    /**
     * Dokumen perpindahan yang masih berjalan (belum diterima/dibatalkan) untuk mushaf ini.
     *
     * Dipakai sebagai penjaga: mushaf yang sedang dalam perjalanan antar gudang tidak boleh
     * dimasukkan ke dokumen kedua — kalau boleh, dua gudang bisa menerimanya dan lokasinya
     * menjadi tidak bisa ditentukan.
     */
    /**
     * Kode dokumen perpindahan TERBUKA yang memuat resi ini, dibaca lewat pembacaan
     * BERKUNCI (bukan snapshot transaksi).
     *
     * Dipakai jalur yang menulis lokasi/isi kerdus resi (addItemSafe, kolam packing
     * kardus): dokumen yang baru saja dibuat transaksi lain harus terlihat, kalau tidak
     * resi yang sedang dipindah gudang tetap bisa dikemas di gudang asal.
     */
    public function kodeTransferMushafTerbukaTerkunci(): ?string
    {
        return DB::table('transfer_mushaf_items')
            ->join('transfer_mushaf', 'transfer_mushaf.id', '=', 'transfer_mushaf_items.transfer_mushaf_id')
            ->where('transfer_mushaf_items.pengiriman_id', $this->id)
            ->where('transfer_mushaf.status', TransferMushaf::STATUS_DIPROSES)
            ->lockForUpdate()
            ->value('transfer_mushaf.kode');
    }

    public function transferMushafTerbuka(): ?TransferMushaf
    {
        return TransferMushaf::query()
            ->where('status', TransferMushaf::STATUS_DIPROSES)
            ->whereHas('items', fn ($q) => $q->where('pengiriman_id', $this->id))
            ->first();
    }

    /**
     * Apakah status ini termasuk tahap gudang (tempat lokasi mushaf ditentukan)?
     *
     * Sumbernya `config/pengiriman.php` — daftar yang sama yang dipakai UI dan penjaga
     * perpindahan status, jadi menambah/mengurangi tahap gudang tidak perlu dua tempat.
     */
    public function statusSlugAdaDiTahapGudang(int $statusId): bool
    {
        $slug = DB::table('status_pengiriman')->where('id', $statusId)->value('slug');

        if (! $slug) {
            return false;
        }

        return in_array($slug, config('pengiriman.warehouse_stage_slugs', []), true);
    }

    /**
     * Alasan resi ini BELUM boleh dimuat ke kurir, atau null bila boleh.
     *
     * Satu aturan untuk SEMUA jalur muat (pindai resi, pindai kerdus, pilih dari daftar,
     * halaman kurir): statusnya harus siap diantar, dan bila resi ini ada di kerdus,
     * kerdusnya harus sudah lolos verifikasi isi oleh distribusi. Tanpa syarat kedua,
     * kerdus yang belum dicek cukup dimuat lewat pindai resi satuan.
     */
    public function alasanBelumBolehDimuat(): ?string
    {
        if (! in_array($this->status?->slug, self::SLUG_SIAP_DIMUAT, true)) {
            return "Resi {$this->no_resi} belum siap diantar (status: ".($this->status->nama ?? '-').').';
        }

        return $this->packingItem?->packingBox?->alasanBelumBolehDimuat();
    }

    /**
     * Scope: Has resi number
     */
    public function scopeHasResi($query)
    {
        return $query->whereNotNull('no_resi');
    }

    /**
     * Scope: No resi yet
     */
    public function scopeNoResi($query)
    {
        return $query->whereNull('no_resi');
    }

    /**
     * Scope: By status
     */
    public function scopeByStatus($query, $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    /**
     * Scope: Pending (default status)
     */
    public function scopePending($query)
    {
        return $query->where('status_id', StatusPengiriman::getDefaultStatusId());
    }

    /**
     * Scope: Has alamat tujuan
     */
    public function scopeHasAlamat($query)
    {
        return $query->whereNotNull('alamat_tujuan');
    }

    /**
     * Scope: No alamat yet
     */
    public function scopeNoAlamat($query)
    {
        return $query->whereNull('alamat_tujuan');
    }

    /**
     * Accessors
     */
    public function getStatusNameAttribute()
    {
        return $this->status ? $this->status->nama : 'Unknown';
    }

    public function getStatusColorAttribute()
    {
        return $this->status ? $this->status->warna : 'gray';
    }

    public function getFormattedTanggalWakafAttribute()
    {
        return $this->tanggal_wakaf ? $this->tanggal_wakaf->format('d/m/Y') : null;
    }

    public function getHasAlamatAttribute()
    {
        return ! empty($this->alamat_tujuan);
    }

    public function getTrackingUrlAttribute()
    {
        return route('public.tracking', $this->no_resi);
    }

    public function getHasSertifikatAttribute()
    {
        return $this->sertifikat()->exists();
    }

    public function getSertifikatNumberAttribute()
    {
        return $this->sertifikat?->nomor_sertifikat;
    }

    /**
     * Helper: Check if no_resi format is valid
     */
    public function isValidNoResiFormat(?string $noResi = null): bool
    {
        $resi = $noResi ?: $this->no_resi;

        return (bool) preg_match('/^EQ-\d{4}-\d{5}$/', $resi);
    }

    /**
     * Helper: Parse no_resi information
     */
    public function parseNoResi(?string $noResi = null)
    {
        $resi = $noResi ?: $this->no_resi;

        if (! $this->isValidNoResiFormat($resi)) {
            return null;
        }

        $parts = explode('-', $resi);

        return [
            'prefix' => $parts[0], // EQ
            'tahun' => $parts[1],  // YYYY
            'nomor' => (int) $parts[2], // XXXXX
            'nomor_urut' => $parts[2], // XXXXX dengan leading zero
        ];
    }

    /**
     * Helper: Update status dengan history
     * Dibungkus transaksi + row lock untuk mencegah race condition
     * (dua update bersamaan → history ganda / status saling timpa)
     */
    /**
     * Ubah status HANYA bila boleh: bukan dari status final, dan tidak mundur (kecuali
     * ke "batal"). Aturannya dicek pada baris yang DIKUNCI — bukan pada instance yang
     * dibaca sebelumnya — supaya dua perubahan bersamaan (mis. distribusi menyelesaikan
     * muatan sementara kurir memindahkan status perjalanan) tidak bisa mengeluarkan resi
     * dari "diterima" hanya karena keduanya membaca status lama.
     *
     * @return 'sama'|'berhasil'|'ditolak'
     */
    public function ubahStatusBilaBoleh(StatusPengiriman $statusBaru, ?string $catatan = null, ?int $userId = null): string
    {
        return \DB::transaction(function () use ($statusBaru, $catatan, $userId) {
            $terkunci = static::whereKey($this->id)->lockForUpdate()->first();

            if (! $terkunci) {
                return 'ditolak';
            }

            if ((int) $terkunci->status_id === (int) $statusBaru->id) {
                return 'sama';
            }

            $statusSekarang = StatusPengiriman::find($terkunci->status_id);

            if ($statusSekarang?->is_final) {
                return 'ditolak';
            }

            if ($statusBaru->slug !== 'batal' && $statusSekarang && $statusBaru->urutan < $statusSekarang->urutan) {
                return 'ditolak';
            }

            // Lihat boot(): "Diterima" hanya dari "Tiba di Tujuan". Ditolak di sini lebih
            // dulu supaya penyelesaian muatan melewati resi yang belum tiba, bukan gagal.
            if ($statusBaru->slug === 'diterima' && $statusSekarang?->slug !== self::SLUG_TIBA) {
                return 'ditolak';
            }

            $terkunci->updateStatus($statusBaru->id, $catatan, $userId);

            return 'berhasil';
        });
    }

    public function updateStatus($newStatusId, ?string $catatan = null, ?int $userId = null)
    {
        return \DB::transaction(function () use ($newStatusId, $catatan, $userId) {
            // Lock baris untuk mencegah update bersamaan
            $locked = static::where('id', $this->id)->lockForUpdate()->first();

            if (! $locked) {
                throw new \Exception('Pengiriman tidak ditemukan');
            }

            $oldStatus = $locked->status_id;

            // Skip jika status sama
            if ((int) $oldStatus === (int) $newStatusId) {
                return $locked;
            }

            // Update status pada instance terkunci
            $atribut = ['status_id' => $newStatusId];

            // LOKASI MUSHAF (2026-10-15): resi yang bergerak di tahap gudang milik gudang
            // pelakunya. Hanya diisi bila BELUM ADA nilainya: sekali lokasi ditentukan, yang
            // boleh mengubahnya hanya dokumen perpindahan antar gudang — bukan sembarang
            // perubahan status oleh petugas lain. Tanpa penjagaan ini, lokasi mushaf bisa
            // berpindah hanya karena ada orang membuka daftar dari gudang lain.
            if ($locked->gudang_id === null) {
                $gudangPelaku = auth()->user()?->gudang_id;

                if ($gudangPelaku && $this->statusSlugAdaDiTahapGudang($newStatusId)) {
                    $atribut['gudang_id'] = $gudangPelaku;
                }
            }

            // Catat waktu terima, sekali saja. Tanpa ini kolom received_at tetap
            // null selamanya dan tampilan yang bergantung padanya selalu
            // melaporkan "belum diterima" walau statusnya sudah diterima.
            if (StatusPengiriman::query()->whereKey($newStatusId)->value('slug') === 'diterima') {
                $atribut['received_at'] = $locked->received_at ?? now();
            }

            $locked->update($atribut);

            // Create status history
            StatusHistory::create([
                'pengiriman_id' => $locked->id,
                'status_from' => $oldStatus,
                'status_to' => $newStatusId,
                'catatan' => $catatan,
                'created_by' => $userId ?: auth()->id() ?? User::query()->value('id'),
            ]);

            // Sinkronkan instance asli agar caller melihat nilai terbaru
            $this->setRawAttributes($locked->getAttributes());
            $this->syncOriginal();

            return $this;
        });
    }

    /**
     * Helper: Generate QR Code data
     */
    public function generateQRData()
    {
        return [
            'no_resi' => $this->no_resi,
            'donatur' => $this->donatur->nama_donatur ?? 'Unknown',
            'jenis_quran' => $this->jenisQuran->nama_jenis ?? 'Unknown',
            'jumlah_quran' => $this->jumlah_quran,
            'tanggal_wakaf' => $this->formatted_tanggal_wakaf,
            'url' => $this->tracking_url,
            'timestamp' => now()->timestamp,
        ];
    }

    /**
     * QR ringkas untuk ditampilkan di daftar kerdus / label.
     *
     * Isinya SENGAJA hanya no_resi — sama persis dengan QR yang sudah dicetak
     * dan dipindai gudang (lihat QRCodeController::buildQRData). Membuat format
     * kedua akan menghasilkan dua QR berbeda untuk satu barang yang sama, dan
     * pemindai yang sudah ada tidak akan mengenali yang baru.
     *
     * Dipakai untuk Quran (A5/A6) maupun Iqra: keduanya baris Pengiriman dengan
     * jenis_quran_id sendiri, jadi QR-nya memang terpisah dan tautan tracking-nya
     * mengarah ke resi masing-masing.
     *
     * Memakai SVG, bukan PNG: satu kerdus Iqra berisi sampai 160 eks dan
     * pembuatan 160 QR PNG memakan ~9 detik (SVG ~0,6 detik) dengan ukuran
     * hasil yang sama. Format ini juga sama dengan QR per-resi yang disimpan
     * QRCodeController, jadi tidak ada dua rupa QR untuk barang yang sama.
     */
    public function getResiQRBase64(): string
    {
        $image = QrCode::format('svg')
            ->size(200)
            ->margin(1)
            ->errorCorrection('L')
            ->generate((string) $this->no_resi);

        return 'data:image/svg+xml;base64,'.base64_encode($image);
    }

    /**
     * Kode jenis untuk pembeda ringkas di daftar (A5 / A6 / IQRO).
     */
    public function getKodeJenisAttribute(): ?string
    {
        return $this->jenisQuran->kode_jenis ?? null;
    }
}
