<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PackingBox extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_packing_task_id',
        'kode_kerdus',
        'jenis_quran_id',
        'assigned_user_id',
        'assignment_type',
        'is_shared_box',
        'created_by_supervisor',
        'target_completion_date',
        'kapasitas',
        'jumlah_terisi',
        'status',
        'sealed_at',
        'seal_code',
        'box_metadata',
    ];

    protected $casts = [
        'sealed_at' => 'datetime',
        'target_completion_date' => 'date',
        'is_shared_box' => 'boolean',
        'box_metadata' => 'array',
    ];

    /**
     * Status constants
     */
    const STATUS_EMPTY = 'empty';

    const STATUS_FILLING = 'filling';

    const STATUS_FULL = 'full';

    const STATUS_SEALED = 'sealed';

    /**
     * Relationships
     */
    public function dailyPackingTask()
    {
        return $this->belongsTo(DailyPackingTask::class);
    }

    public function packingItems()
    {
        return $this->hasMany(PackingItem::class);
    }

    public function jenisQuran()
    {
        return $this->belongsTo(JenisQuran::class, 'jenis_quran_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdBySupervisor()
    {
        return $this->belongsTo(User::class, 'created_by_supervisor');
    }

    public function sharedBoxAssignments()
    {
        return $this->hasMany(SharedBoxAssignment::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_EMPTY, self::STATUS_FILLING]);
    }

    public function scopeFilling($query)
    {
        return $query->where('status', self::STATUS_FILLING);
    }

    /**
     * Get jenis-specific badge styling
     */
    public function getJenisBadgeAttribute()
    {
        if (! $this->jenisQuran) {
            return ['class' => 'bg-gray-100 text-gray-800', 'label' => 'Belum ditentukan'];
        }

        return [
            'class' => $this->jenisQuran->badge_class,
            'label' => $this->jenisQuran->nama_jenis,
        ];
    }

    public function getRemainingSpaceAttribute()
    {
        return $this->kapasitas - $this->jumlah_terisi;
    }

    public function getIsFullAttribute()
    {
        return $this->jumlah_terisi >= $this->kapasitas;
    }

    public function getIsSealedAttribute()
    {
        return $this->status === self::STATUS_SEALED;
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->kapasitas == 0) {
            return 0;
        }

        return round(($this->jumlah_terisi / $this->kapasitas) * 100, 2);
    }

    /**
     * Methods
     */
    public function addItem(Pengiriman $pengiriman, $userId)
    {
        return $this->addItemSafe($pengiriman, $userId);
    }

    /**
     * Add item to box with race condition safety - enhanced version
     */
    public function addItemSafe(Pengiriman $pengiriman, $userId)
    {
        return DB::transaction(function () use ($pengiriman, $userId) {
            // Lock this box record to prevent concurrent modifications
            $lockedBox = self::where('id', $this->id)->lockForUpdate()->first();

            if (! $lockedBox) {
                throw new \Exception('Kerdus tidak ditemukan atau sudah dihapus');
            }

            // Use the locked instance for all operations
            $this->setRawAttributes($lockedBox->getAttributes());
            $this->syncOriginal();

            // Capacity validation with locked data
            if ($this->jumlah_terisi >= $this->kapasitas) {
                throw new \Exception('Kerdus sudah penuh');
            }

            if ($this->is_sealed) {
                throw new \Exception('Kerdus sudah disegel');
            }

            // Validasi jenis Quran - 1 kerdus hanya 1 jenis
            if ($this->jenis_quran_id && $this->jenis_quran_id != $pengiriman->jenis_quran_id) {
                $jenisKerdus = $this->jenisQuran->nama_jenis ?? 'Unknown';
                $jenisItem = $pengiriman->jenisQuran->nama_jenis ?? 'Unknown';
                throw new \Exception("Kerdus ini khusus untuk {$jenisKerdus}. Item yang akan dimasukkan adalah {$jenisItem}. Tidak bisa dicampur!");
            }

            // Set jenis Quran dan update kapasitas jika kerdus masih kosong
            if (! $this->jenis_quran_id) {
                $jenisQuran = $pengiriman->jenisQuran;
                $newCapacity = $jenisQuran ? $jenisQuran->getDefaultCapacity() : 20;

                $this->update([
                    'jenis_quran_id' => $pengiriman->jenis_quran_id,
                    'kapasitas' => $newCapacity,
                ]);
            }

            // Start filling if empty
            if ($this->status === self::STATUS_EMPTY) {
                $this->update(['status' => self::STATUS_FILLING]);
            }

            // Add item with quantity consideration
            $quantity = $pengiriman->jumlah_quran ?? 1;

            // Final capacity check with exact numbers
            if (($this->jumlah_terisi + $quantity) > $this->kapasitas) {
                $available = $this->kapasitas - $this->jumlah_terisi;
                throw new \Exception("Tidak cukup ruang di kerdus. Tersedia: {$available}, dibutuhkan: {$quantity}");
            }

            // Get current count of items with lock to prevent race conditions in sequence
            $currentItemCount = $this->packingItems()->lockForUpdate()->count();

            $item = $this->packingItems()->create([
                'pengiriman_id' => $pengiriman->id,
                'packed_by' => $userId,
                'packed_at' => now(),
                'urutan_dalam_box' => $currentItemCount + 1,
                'quantity' => $quantity,
            ]);

            // Atomic update of count with explicit locking
            $this->increment('jumlah_terisi', $quantity);

            // Re-check capacity after increment to handle edge cases
            $this->refresh();
            if ($this->jumlah_terisi >= $this->kapasitas) {
                $this->markAsFull();
            }

            return $item;
        }, 3); // Retry up to 3 times for deadlock resolution
    }

    /**
     * Mark box as full
     */
    public function markAsFull()
    {
        $this->update(['status' => self::STATUS_FULL]);

        // Auto seal if configured
        if (config('packing.auto_seal_on_full', true)) {
            $this->seal();
        }
    }

    /**
     * Seal the box
     * ✅ Transaction-wrapped untuk atomicity
     */
    public function seal()
    {
        return DB::transaction(function () {
            if ($this->is_sealed) {
                return;
            }

            $this->update([
                'status' => self::STATUS_SEALED,
                'sealed_at' => now(),
                'seal_code' => $this->generateSealCode(),
            ]);

            // Activate next box
            $task = $this->dailyPackingTask;
            $nextBox = $task->getNextBox();
            if ($nextBox) {
                $nextBox->update(['status' => self::STATUS_FILLING]);
            }
        }, 3); // Retry up to 3 times for deadlock
    }

    /**
     * Generate unique seal code
     */
    private function generateSealCode()
    {
        return strtoupper(substr(md5($this->kode_kerdus.now()), 0, 8));
    }

    /**
     * Get items with relations
     */
    public function getItemsWithDetails()
    {
        return $this->packingItems()
            ->with(['pengiriman' => function ($query) {
                $query->with(['donatur', 'jenisQuran', 'wakafItem']);
            }])
            ->orderBy('urutan_dalam_box')
            ->get();
    }

    /**
     * Get box content summary
     */
    public function getContentSummary()
    {
        $items = $this->packingItems()->with(['pengiriman.donatur', 'pengiriman.jenisQuran', 'pengiriman.wakafItem', 'packedByUser'])->get();

        return [
            'total_items' => $items->count(),
            'jenis_quran' => $this->jenisQuran->nama_jenis ?? 'Belum ditentukan',
            'items' => $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'no_resi' => $item->pengiriman->no_resi,
                    'donatur' => $item->pengiriman->donatur->nama_donatur ?? 'N/A',
                    'wakif' => $item->pengiriman->wakafItem->wakif_name ?? $item->pengiriman->donatur->nama_donatur ?? 'N/A',
                    'urutan_dalam_box' => $item->urutan_dalam_box,
                    'packed_at' => $item->packed_at->format('d/m/Y H:i'),
                    'packed_by_name' => $item->packedByUser->name ?? 'Unknown',
                ];
            }),
            'box_info' => [
                'kode_kerdus' => $this->kode_kerdus,
                'status' => $this->status,
                'kapasitas' => $this->kapasitas,
                'terisi' => $this->jumlah_terisi,
                'progress_percentage' => $this->progress_percentage,
                'seal_code' => $this->seal_code,
                'sealed_at' => $this->sealed_at?->format('d/m/Y H:i'),
                'created_at' => $this->created_at->format('d/m/Y H:i'),
            ],
        ];
    }

    /**
     * Get box status with color
     */
    public function getStatusInfo()
    {
        $statusMap = [
            'empty' => ['label' => 'Kosong', 'color' => 'gray', 'bg' => 'bg-gray-100', 'text' => 'text-gray-800'],
            'filling' => ['label' => 'Sedang Diisi', 'color' => 'blue', 'bg' => 'bg-blue-100', 'text' => 'text-blue-800'],
            'full' => ['label' => 'Penuh', 'color' => 'yellow', 'bg' => 'bg-yellow-100', 'text' => 'text-yellow-800'],
            'sealed' => ['label' => 'Tersegel', 'color' => 'green', 'bg' => 'bg-green-100', 'text' => 'text-green-800'],
        ];

        return $statusMap[$this->status] ?? $statusMap['empty'];
    }

    /**
     * Check if box can accept specific jenis quran
     */
    public function canAcceptJenisQuran($jenisQuranId)
    {
        // Jika kerdus kosong, bisa terima jenis apapun
        if (! $this->jenis_quran_id) {
            return true;
        }

        // Jika sudah ada jenis, hanya bisa terima jenis yang sama
        return $this->jenis_quran_id == $jenisQuranId;
    }

    /**
     * Get available space for specific jenis quran
     */
    public function getAvailableSpaceForJenis($jenisQuranId)
    {
        if (! $this->canAcceptJenisQuran($jenisQuranId)) {
            return 0;
        }

        return $this->remaining_space;
    }

    /**
     * Generate Box QR Code for bulk operations
     */
    public function generateBoxQR()
    {
        // Simplified: Only box code for cleaner QR
        return QrCode::format('png')
            ->size(200)
            ->margin(1)
            ->generate($this->kode_kerdus);
    }

    /**
     * Get Box QR data as base64 string for embedding in HTML
     */
    public function getBoxQRBase64()
    {
        return 'data:image/png;base64,'.base64_encode($this->generateBoxQR());
    }

    /**
     * Get Box QR data structure for API/JSON responses
     * Now returns simple string instead of complex JSON
     */
    public function getBoxQRData()
    {
        // Simplified: Only return box code
        return $this->kode_kerdus;
    }

    /**
     * Validate if scanned QR data belongs to this box
     */
    public function validateQRData($qrData)
    {
        // Handle both old JSON format and new simple format
        if (is_string($qrData)) {
            // Try JSON decode for backward compatibility
            $decoded = json_decode($qrData, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Old JSON format
                return isset($decoded['type']) &&
                       $decoded['type'] === 'box' &&
                       isset($decoded['kode_kerdus']) &&
                       $decoded['kode_kerdus'] === $this->kode_kerdus;
            }

            // New simple format - just the box code
            return $qrData === $this->kode_kerdus;
        }

        // If already array (old format)
        if (is_array($qrData)) {
            return isset($qrData['type']) &&
                   $qrData['type'] === 'box' &&
                   isset($qrData['kode_kerdus']) &&
                   $qrData['kode_kerdus'] === $this->kode_kerdus;
        }

        return false;
    }
}
