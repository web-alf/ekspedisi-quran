<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class DailyPackingTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        // LOKASI MUSHAF (2026-10-15). Tanpa baris ini, `$tugas->update(['gudang_id' => ...])`
        // MEMBUANG nilainya tanpa galat sama sekali — terbukti di uji panel packing: responsnya
        // sukses, tugasnya tetap tanpa gudang. Jebakan yang sama sudah dua kali kena di kolom
        // lain (pengiriman.mushaf_request_id, packing_boxes.gudang_id).
        'gudang_id',
        // Jenis mushaf tugas (28/10/2026): resi berjenis lain ditolak saat scan; varian
        // terjemahan hanya keterangan (resi belum menyimpan varian).
        'jenis_quran_id',
        'terjemahan',
        'tanggal_tugas',
        'total_target',
        'total_selesai',
        'sisa_kemarin',
        'status',
        'assignment_method',
        'box_breakdown',
        'has_shared_boxes',
        'total_boxes_assigned',
        'total_boxes_completed',
        'assigned_at',
        'assigned_by',
        'started_at',
        'first_scan_at',
        'completed_at',
        'expired_at',
        'notes',
    ];

    protected $casts = [
        'tanggal_tugas' => 'date',
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'first_scan_at' => 'datetime',
        'completed_at' => 'datetime',
        'expired_at' => 'datetime',
        'box_breakdown' => 'array',
        'has_shared_boxes' => 'boolean',
        'terjemahan' => 'boolean',
    ];

    /**
     * Tugas lahir membawa gudang staf yang ditugasi (keputusan pemilik sistem, 16/10/2026).
     *
     * Penugasan yang dibuat tanpa ada yang login (dulu cron `packing:daily-assignment` pukul
     * 06:00; sejak 27/10/2026 tugas dibuat manual oleh PJ/SPV) tidak membawa gudang penugasa,
     * jadi cadangan \"gudang penugasa\" di gudangKerdus() selalu kosong — seluruh 376 tugas
     * produksi lahir tanpa gudang. Diisi di model, bukan di tiap pemanggil, karena tugas dibuat
     * dari beberapa jalur (penugasan manual, job massal, kerdus bersama). Gudang yang diisi
     * eksplisit oleh pemanggil (SPV/PJ) tetap dihormati.
     */
    protected static function booted(): void
    {
        static::creating(function (DailyPackingTask $tugas): void {
            if ($tugas->gudang_id === null && $tugas->user_id) {
                $tugas->gudang_id = User::whereKey($tugas->user_id)->value('gudang_id');
            }
        });
    }

    /**
     * Status constants
     */
    const STATUS_ASSIGNED = 'assigned';

    const STATUS_IN_PROGRESS = 'in_progress';

    const STATUS_COMPLETED = 'completed';

    const STATUS_EXPIRED = 'expired';

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Gudang tempat tugas ini dikerjakan (Fase 2 rombakan gudang).
     *
     * Diisi saat SPV Gudang/Manager Repacking assign target — berasal dari
     * gudang penugasa. NULL berarti tugas tidak bertagih gudang (penugasan
     * admin/otomatis tanpa gudang).
     */
    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    /**
     * Jenis mushaf yang boleh dikemas dalam tugas ini; null = tanpa batasan jenis.
     */
    public function jenisQuran(): BelongsTo
    {
        return $this->belongsTo(JenisQuran::class);
    }

    /**
     * Label jenis tugas seperti di modal Assign Target ("A5 Terjemahan", "Iqro"); null bila
     * tugas tidak dibatasi jenis.
     */
    public function labelJenis(): ?string
    {
        return $this->jenisQuran?->labelDenganVarian($this->terjemahan);
    }

    /**
     * Apakah resi berjenis ini ditolak oleh tugas? Hanya JENIS yang dijaga; varian terjemahan
     * belum tersimpan di resi sehingga tidak bisa diperiksa.
     */
    public function menolakJenis(?int $jenisQuranId): bool
    {
        return $this->jenis_quran_id !== null && (int) $this->jenis_quran_id !== (int) $jenisQuranId;
    }

    /**
     * Pesan untuk staf yang memindai/mencatat jenis di luar tugasnya. Jenis resi dimuat ulang
     * dari id-nya: relasi pada resi sering dimuat tanpa kolom kode_jenis.
     */
    public function pesanJenisBerbeda(?int $jenisResiId): string
    {
        $jenisLain = JenisQuran::find($jenisResiId)?->labelSingkat() ?? 'jenis lain';

        return "Tugas Anda hari ini khusus {$this->labelJenis()}, sedangkan resi ini {$jenisLain}. Kemas resi ini lewat tugas jenis {$jenisLain}, atau minta SPV mengubah jenis tugas Anda.";
    }

    public function packingBoxes()
    {
        return $this->hasMany(PackingBox::class);
    }

    public function taskItems()
    {
        return $this->hasMany(DailyPackingTaskItem::class);
    }

    public function notifications()
    {
        return $this->hasMany(PackingNotification::class);
    }

    public function targetBreakdowns()
    {
        return $this->hasMany(DailyPackingTaskTarget::class);
    }

    public function sharedBoxAssignments()
    {
        return $this->hasMany(SharedBoxAssignment::class);
    }

    /**
     * Scopes
     */
    public function scopeToday($query)
    {
        return $query->whereDate('tanggal_tugas', today());
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS]);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Accessors
     */
    public function getProgressPercentageAttribute()
    {
        if ($this->total_target == 0) {
            return 0;
        }

        return round(($this->total_selesai / $this->total_target) * 100, 2);
    }

    public function getRemainingAttribute()
    {
        return $this->total_target - $this->total_selesai;
    }

    public function getIsCompletedAttribute()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getIsExpiredAttribute()
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function getIsActiveAttribute()
    {
        return in_array($this->status, [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS]);
    }

    /**
     * Methods
     */
    public function startTask()
    {
        if ($this->status === self::STATUS_ASSIGNED) {
            $this->update([
                'status' => self::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);
        }
    }

    public function completeTask()
    {
        if ($this->total_selesai >= $this->total_target) {
            $this->update([
                'status' => self::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            // Update user performance
            $this->updateUserPerformance();
        }
    }

    public function expireTask()
    {
        if ($this->is_active && $this->tanggal_tugas->lt(today())) {
            $this->update([
                'status' => self::STATUS_EXPIRED,
                'expired_at' => now(),
            ]);
        }
    }

    public function incrementProgress($count = 1)
    {
        $this->increment('total_selesai', $count);

        // increment() hanya menambah angka DI MEMORI instance ini. Instance yang dimuat
        // sebelum baris tugasnya dikunci (lewat relasi, atau snapshot transaksi) membawa
        // angka basi: dua pindai bersamaan di 79/80 menulis 80 di DB tetapi 80 tidak
        // pernah terlihat di sini, sehingga tugas tidak pernah ditandai selesai. Angka
        // pembanding diambil ulang lewat pembacaan BERKUNCI, yang selalu membaca nilai
        // terbaru.
        $terkini = static::whereKey($this->getKey())
            ->lockForUpdate()
            ->first(['id', 'total_selesai', 'total_target', 'status']);

        if ($terkini) {
            $kolom = ['total_selesai', 'total_target', 'status'];
            $this->forceFill($terkini->only($kolom))->syncOriginalAttributes($kolom);
        }

        // Start task if not started
        if ($this->status === self::STATUS_ASSIGNED) {
            $this->startTask();
        }

        // Complete task if target reached — sekali saja: completeTask() juga menambah
        // statistik performa harian, yang tidak boleh terhitung dua kali.
        if ($this->status !== self::STATUS_COMPLETED && $this->total_selesai >= $this->total_target) {
            $this->completeTask();
        }
    }

    /**
     * Generate packing boxes based on assigned item types
     */
    public function generateJenisBasedPackingBoxes()
    {
        // Analyze assigned items by jenis
        $jenisAnalysis = $this->taskItems()
            ->with('pengiriman.jenisQuran')
            ->get()
            ->groupBy('pengiriman.jenis_quran_id')
            ->map(function ($items, $jenisId) {
                $jenis = $items->first()->pengiriman->jenisQuran;
                $totalItems = $items->count();
                $capacity = $jenis->getDefaultCapacity();
                $boxesNeeded = ceil($totalItems / $capacity);

                return [
                    'jenis_id' => $jenisId,
                    'jenis_name' => $jenis->nama_jenis,
                    'jenis_code' => $jenis->kode_jenis,
                    'capacity' => $capacity,
                    'total_items' => $totalItems,
                    'boxes_needed' => $boxesNeeded,
                ];
            });

        $boxes = [];
        $boxSequence = 1;
        $date = $this->tanggal_tugas->format('Ymd');

        // Generate boxes for each jenis
        foreach ($jenisAnalysis as $analysis) {
            for ($i = 1; $i <= $analysis['boxes_needed']; $i++) {
                $box = $this->packingBoxes()->create([
                    'kode_kerdus' => $this->generateJenisBoxCode($date, $analysis['jenis_code'], $boxSequence),
                    'jenis_quran_id' => $analysis['jenis_id'],
                    'kapasitas' => $analysis['capacity'],
                    'status' => count($boxes) === 0 ? 'filling' : 'empty', // First box is active
                ]);

                $boxes[] = $box;
                $boxSequence++;
            }
        }

        return $boxes;
    }

    /**
     * Generate jenis-specific box code
     */
    private function generateJenisBoxCode($date, $jenisCode, $sequence)
    {
        $userId = str_pad($this->user_id, 3, '0', STR_PAD_LEFT);
        $seq = str_pad($sequence, 2, '0', STR_PAD_LEFT);

        return "KB-{$date}-{$userId}-{$jenisCode}-{$seq}";
        // Example: KB-20250719-001-A5-01, KB-20250719-001-A6-01, KB-20250719-001-IQRO-01
    }

    /**
     * Get current box for specific jenis
     */
    public function getCurrentBoxForJenis($jenisQuranId)
    {
        return $this->packingBoxes()
            ->where('jenis_quran_id', $jenisQuranId)
            ->where('status', 'filling')
            ->first();
    }

    /**
     * Check if there are active boxes with different jenis
     */
    public function getActiveBoxesOtherThanJenis($jenisQuranId)
    {
        return $this->packingBoxes()
            ->where('jenis_quran_id', '!=', $jenisQuranId)
            ->where('status', 'filling')
            ->with('jenisQuran')
            ->get();
    }

    /**
     * Get box status info for warning system
     */
    public function getBoxStatusForJenis($jenisQuranId)
    {
        $currentBox = $this->getCurrentBoxForJenis($jenisQuranId);
        $otherActiveBoxes = $this->getActiveBoxesOtherThanJenis($jenisQuranId);

        return [
            'current_box' => $currentBox,
            'other_active_boxes' => $otherActiveBoxes,
            'has_other_active' => $otherActiveBoxes->isNotEmpty(),
            'warning_needed' => $otherActiveBoxes->isNotEmpty() && ! $currentBox,
        ];
    }

    /**
     * Get or activate next box for specific jenis (HYBRID DYNAMIC) - Race condition safe
     */
    public function getOrActivateBoxForJenis($jenisQuranId)
    {
        return DB::transaction(function () use ($jenisQuranId) {
            // Use default isolation and explicit row locks for consistency

            // Try to get current filling box for this jenis with pessimistic lock
            $currentBox = $this->packingBoxes()
                ->where('jenis_quran_id', $jenisQuranId)
                ->where('status', 'filling')
                ->lockForUpdate()
                ->first();

            // If we have a current box, check if it still has capacity
            if ($currentBox) {
                // Refresh to get latest capacity count with lock
                $currentBox->refresh();

                if (! $currentBox->is_full && $currentBox->jumlah_terisi < $currentBox->kapasitas) {
                    return $currentBox;
                }

                // Box is full, mark it as full
                if ($currentBox->jumlah_terisi >= $currentBox->kapasitas) {
                    $currentBox->markAsFull();
                }
            }

            // Get next empty box for this jenis with lock
            $nextBox = $this->packingBoxes()
                ->where('jenis_quran_id', $jenisQuranId)
                ->where('status', 'empty')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($nextBox) {
                // Double check status after lock acquisition
                if ($nextBox->status === 'empty') {
                    $nextBox->update(['status' => 'filling']);

                    return $nextBox;
                }

                // Status changed during lock wait, recurse to try again
                return $this->getOrActivateBoxForJenis($jenisQuranId);
            }

            // HYBRID DYNAMIC: Create new box on-demand with race condition protection
            return $this->createDynamicBoxForJenisSafe($jenisQuranId);
        }, 3); // Retry up to 3 times for deadlock resolution
    }

    /**
     * Gudang tempat kerdus tugas ini dibuat (LOKASI MUSHAF, 2026-10-15).
     *
     * Urutannya: gudang TUGAS (diisi SPV/PJ saat menugaskan) → gudang PEKERJA yang ditugasi
     * → gudang PENUGASA yang sedang aktif. Dua yang terakhir adalah cadangan yang penting
     * karena di produksi seluruh 354 tugas belum bertagih gudang: tanpa cadangan itu, kerdus
     * tidak akan pernah punya lokasi walau akunnya jelas milik gudang tertentu.
     *
     * Mengembalikan null bila ketiganya kosong — itu berarti "belum diketahui", bukan
     * ditebak. UI menampilkan keadaan itu apa adanya supaya bisa dibetulkan.
     */
    public function gudangKerdus(): ?int
    {
        if ($this->gudang_id) {
            return (int) $this->gudang_id;
        }

        $pekerja = $this->relationLoaded('user')
            ? $this->user?->gudang_id
            : User::whereKey($this->user_id)->value('gudang_id');

        if ($pekerja) {
            return (int) $pekerja;
        }

        $penugasa = auth()->user()?->gudang_id;

        return $penugasa ? (int) $penugasa : null;
    }

    /**
     * Create new box dynamically for specific jenis - Race condition safe
     */
    public function createDynamicBoxForJenisSafe($jenisQuranId)
    {
        // This method should be called within a transaction
        $jenis = JenisQuran::lockForUpdate()->find($jenisQuranId);
        if (! $jenis) {
            return null;
        }

        $date = $this->tanggal_tugas->format('Ymd');

        // Get box count with lock to prevent duplicate sequence numbers
        $boxCount = $this->packingBoxes()
            ->lockForUpdate()
            ->count() + 1;

        // Generate unique box code with collision detection
        $attempts = 0;
        $maxAttempts = 5;

        while ($attempts < $maxAttempts) {
            $boxCode = $this->generateDynamicBoxCode($date, $jenis->kode_jenis, $boxCount + $attempts);

            // Check if code already exists
            $existingBox = PackingBox::where('kode_kerdus', $boxCode)
                ->lockForUpdate()
                ->first();

            if (! $existingBox) {
                // Code is unique, create the box
                return $this->packingBoxes()->create([
                    'kode_kerdus' => $boxCode,
                    'jenis_quran_id' => $jenisQuranId,
                    'kapasitas' => $jenis->getDefaultCapacity(),
                    'status' => 'filling',
                    // LOKASI MUSHAF: kerdus baru langsung membawa gudangnya, dihitung sekali
                    // di sini supaya tidak bergantung lagi pada tugas yang bisa diubah.
                    'gudang_id' => $this->gudangKerdus(),
                ]);
            }

            $attempts++;
        }

        // If we couldn't create a unique code after max attempts
        throw new \Exception('Unable to generate unique box code after '.$maxAttempts.' attempts');
    }

    /**
     * Create new box dynamically for specific jenis (Legacy method - kept for backward compatibility)
     */
    public function createDynamicBoxForJenis($jenisQuranId)
    {
        return $this->createDynamicBoxForJenisSafe($jenisQuranId);
    }

    /**
     * Generate dynamic box code
     */
    private function generateDynamicBoxCode($date, $jenisCode, $sequence)
    {
        $userId = str_pad($this->user_id, 3, '0', STR_PAD_LEFT);
        $seq = str_pad($sequence, 2, '0', STR_PAD_LEFT);

        return "KB-{$date}-{$userId}-{$jenisCode}-{$seq}";
    }

    /**
     * Create dynamic task item for hybrid system
     */
    public function createDynamicTaskItem($pengiriman)
    {
        return $this->taskItems()->create([
            'pengiriman_id' => $pengiriman->id,
            'assigned_at' => now(),
            'is_packed' => false,
        ]);
    }

    /**
     * Generate unique box code
     */
    private function generateBoxCode($date, $sequence)
    {
        $userId = str_pad($this->user_id, 3, '0', STR_PAD_LEFT);
        $seq = str_pad($sequence, 2, '0', STR_PAD_LEFT);

        return "KB-{$date}-{$userId}-{$seq}";
    }

    /**
     * Get current active box
     */
    public function getCurrentBox()
    {
        return $this->packingBoxes()
            ->where('status', 'filling')
            ->first();
    }

    /**
     * Get next empty box
     */
    public function getNextBox()
    {
        return $this->packingBoxes()
            ->where('status', 'empty')
            ->orderBy('id')
            ->first();
    }

    /**
     * Update user performance
     */
    private function updateUserPerformance()
    {
        $bulan = $this->tanggal_tugas->format('Y-m');

        $performance = UserPerformance::firstOrCreate(
            [
                'user_id' => $this->user_id,
                'bulan' => $bulan,
            ],
            [
                'total_target' => 0,
                'total_achieved' => 0,
                'total_hari_kerja' => 0,
                'total_hari_complete' => 0,
            ]
        );

        // Update performance data
        $performance->increment('total_achieved', $this->total_selesai);

        if ($this->is_completed) {
            $performance->increment('total_hari_complete');
        }

        // Calculate achievement rate
        $allTasks = self::where('user_id', $this->user_id)
            ->whereMonth('tanggal_tugas', $this->tanggal_tugas->month)
            ->whereYear('tanggal_tugas', $this->tanggal_tugas->year)
            ->get();

        $totalTarget = $allTasks->sum('total_target');
        $totalAchieved = $allTasks->sum('total_selesai');

        $performance->update([
            'total_target' => $totalTarget,
            'total_achieved' => $totalAchieved,
            'achievement_rate' => $totalTarget > 0 ? ($totalAchieved / $totalTarget * 100) : 0,
            'total_hari_kerja' => $allTasks->count(),
        ]);
    }

    /**
     * Check if should send notification
     */
    public function checkProgressNotification()
    {
        $hour = now()->hour;
        $progress = $this->progress_percentage;

        // Get progress milestones dari config
        $expectedProgress = config('packing.progress_milestones', [
            10 => 25,  // Jam 10: minimal 25%
            12 => 40,  // Jam 12: minimal 40%
            14 => 60,  // Jam 14: minimal 60%
            16 => 80,  // Jam 16: minimal 80%
        ]);

        if (isset($expectedProgress[$hour]) && $progress < $expectedProgress[$hour]) {
            $this->sendProgressNotification($progress, $expectedProgress[$hour]);
        }
    }

    /**
     * Send progress notification
     */
    private function sendProgressNotification($currentProgress, $expectedProgress)
    {
        $level = 'info';
        if ($currentProgress < $expectedProgress / 2) {
            $level = 'critical';
        } elseif ($currentProgress < $expectedProgress * 0.75) {
            $level = 'warning';
        }

        PackingNotification::create([
            'user_id' => $this->user_id,
            'daily_packing_task_id' => $this->id,
            'type' => 'reminder',
            'level' => $level,
            'title' => 'Progress Update',
            'message' => "Progress Anda saat ini {$currentProgress}%, target jam ini adalah {$expectedProgress}%",
            'meta_data' => [
                'current_progress' => $currentProgress,
                'expected_progress' => $expectedProgress,
                'remaining' => $this->remaining,
            ],
        ]);
    }
}
