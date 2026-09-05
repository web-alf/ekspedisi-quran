<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScanStatusRequest;
use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Models\StatusHistory;
use App\Models\StatusPengiriman;
use App\Models\TrackingHistory;
use App\Services\Cache\StatusPengirimanCache;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PengirimanController extends Controller
{
    /**
     * Display a listing of pengiriman
     */
    public function index(Request $request)
    {
        auth()->user()->can(PermissionEnum::SHIPMENTS_READ->value);

        // Check if this is a special mode request
        $mode = $request->get('mode');

        if ($mode === 'generate-qr') {
            return $this->generateQRPage($request);
        }

        if ($mode === 'scan-status') {
            return $this->scanStatus();
        }

        // Default pengiriman index listing
        $query = Pengiriman::with([
            'donatur:id,nama_donatur,kode_donatur,no_hp',
            'wakafItem:id,pengiriman_id,wakif_name,doa_request,relationship_to_donatur',
            'jenisQuran:id,nama_jenis,kode_jenis',
            'status:id,nama,slug,warna',
            'creator:id,name',
            'sertifikat:id,pengiriman_id,nomor_sertifikat,generated_at',
            'mushafRequest:id,pengiriman_id,nama_lembaga,status',
        ]);

        // Search functionality
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('no_resi', 'like', '%'.$request->search.'%')
                    ->orWhere('nama_penerima', 'like', '%'.$request->search.'%')
                    ->orWhere('alamat_tujuan', 'like', '%'.$request->search.'%')
                    ->orWhereHas('donatur', function ($wq) use ($request) {
                        $wq->where('nama_donatur', 'like', '%'.$request->search.'%')
                            ->orWhere('kode_donatur', 'like', '%'.$request->search.'%');
                    });
            });
        }

        // Filter by status
        if ($request->status) {
            $query->where('status_id', $request->status);
        }

        // Filter by jenis Qur'an
        if ($request->jenis_quran) {
            $query->where('jenis_quran_id', $request->jenis_quran);
        }

        // Filter by alamat status
        if ($request->alamat_status) {
            switch ($request->alamat_status) {
                case 'sudah_diset':
                    // Has address and recipient (either from mushaf request or manual input)
                    $query->whereNotNull('alamat_tujuan')
                        ->whereNotNull('nama_penerima');
                    break;
                case 'belum_diset':
                    // Missing address or recipient
                    $query->where(function ($q) {
                        $q->whereNull('alamat_tujuan')
                            ->orWhereNull('nama_penerima');
                    });
                    break;
            }
        }

        // Filter by date range
        if ($request->tanggal_mulai && $request->tanggal_akhir) {
            $query->whereBetween('tanggal_wakaf', [$request->tanggal_mulai, $request->tanggal_akhir]);
        } elseif ($request->tanggal_mulai) {
            $query->whereDate('tanggal_wakaf', '>=', $request->tanggal_mulai);
        } elseif ($request->tanggal_akhir) {
            $query->whereDate('tanggal_wakaf', '<=', $request->tanggal_akhir);
        }

        // Filter by donatur
        if ($request->donatur_id) {
            $query->where('donatur_id', $request->donatur_id);
        }

        // Sort first (before calculating row numbers)
        $sortBy = $request->sort_by ?? 'no_resi';
        $sortOrder = $request->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Handle row number filtering
        $perPage = 100;
        $page = 1;

        if ($request->filled('number_from') && $request->filled('number_to')) {
            // Both from and to specified
            $numberFrom = max(1, (int) $request->number_from);
            $numberTo = max($numberFrom, (int) $request->number_to);

            // Calculate which page the "from" number is on
            $page = (int) ceil($numberFrom / $perPage);

            // Calculate how many items to skip within the page
            $skipInPage = ($numberFrom - 1) % $perPage;

            // Calculate how many items to take
            $itemsToTake = ($numberTo - $numberFrom + 1);

            // Get the paginated results
            $pengiriman = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();

            // Slice the collection to get only the specified range
            $slicedData = collect($pengiriman->items())->slice($skipInPage, $itemsToTake)->values();

            // Rebuild pagination with sliced data
            $pengiriman = new LengthAwarePaginator(
                $slicedData,
                $pengiriman->total(),
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            // Manually set the 'from' property for correct row numbering
            $pengiriman->from = $numberFrom;

        } elseif ($request->filled('number_from')) {
            // Only from specified
            $numberFrom = max(1, (int) $request->number_from);
            $page = (int) ceil($numberFrom / $perPage);

            $pengiriman = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();

            // Slice to start from the specified number
            $skipInPage = ($numberFrom - 1) % $perPage;
            $slicedData = collect($pengiriman->items())->slice($skipInPage)->values();

            $pengiriman = new LengthAwarePaginator(
                $slicedData,
                $pengiriman->total(),
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            $pengiriman->from = $numberFrom;

        } elseif ($request->filled('number_to')) {
            // Only to specified - show from beginning to specified number
            $numberTo = max(1, (int) $request->number_to);
            $lastPage = (int) ceil($numberTo / $perPage);

            $pengiriman = $query->paginate($perPage, ['*'], 'page', $lastPage)->withQueryString();

            // If we're on the last page, slice to end at the specified number
            $takeInPage = (($numberTo - 1) % $perPage) + 1;
            $slicedData = collect($pengiriman->items())->take($takeInPage)->values();

            $pengiriman = new LengthAwarePaginator(
                $slicedData,
                $pengiriman->total(),
                $perPage,
                $lastPage,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

        } else {
            // No number filtering, standard pagination
            $pengiriman = $query->paginate($perPage)->withQueryString();
        }

        return Inertia::render('Admin/Pengiriman/Index', [
            'pengiriman' => $pengiriman,
            'filters' => $request->only(['search', 'status', 'jenis_quran', 'alamat_status', 'tanggal_mulai', 'tanggal_akhir', 'donatur_id', 'sort_by', 'sort_order', 'number_from', 'number_to']),
            'statusList' => StatusPengiriman::active()->select('id', 'nama', 'slug', 'warna')->get(),
            'jenisQuranList' => JenisQuran::active()->select('id', 'nama_jenis', 'kode_jenis')->get(),
            'donaturList' => Donatur::select('id', 'nama_donatur', 'kode_donatur')->orderBy('nama_donatur')->get(),
            'stats' => $this->getOptimizedPengirimanStats(),
        ]);
    }

    /**
     * Get optimized pengiriman statistics using single query
     * Replaces multiple whereHas calls to prevent N+1 query problem
     */
    private function getOptimizedPengirimanStats()
    {
        try {
            // Single query with conditional aggregation - eliminates N+1 problem
            $stats = \DB::table('pengiriman')
                ->join('status_pengiriman', 'pengiriman.status_id', '=', 'status_pengiriman.id')
                ->selectRaw('
                    COUNT(CASE WHEN status_pengiriman.slug = "pemesanan" THEN 1 END) as pemesanan,
                    COUNT(CASE WHEN status_pengiriman.slug = "packing" THEN 1 END) as packing,
                    COUNT(CASE WHEN status_pengiriman.slug = "pengiriman" THEN 1 END) as pengiriman,
                    COUNT(CASE WHEN status_pengiriman.slug = "diterima" THEN 1 END) as diterima
                ')
                ->first();

            return [
                'pemesanan' => (int) $stats->pemesanan,
                'packing' => (int) $stats->packing,
                'pengiriman' => (int) $stats->pengiriman,
                'diterima' => (int) $stats->diterima,
            ];
        } catch (\Exception $e) {
            // Fallback to individual queries if optimization fails
            \Log::warning('Optimized pengiriman stats failed, falling back to individual queries: '.$e->getMessage());

            return [
                'pemesanan' => Pengiriman::whereHas('status', function ($q) {
                    $q->where('slug', 'pemesanan');
                })->count(),
                'packing' => Pengiriman::whereHas('status', function ($q) {
                    $q->where('slug', 'packing');
                })->count(),
                'pengiriman' => Pengiriman::whereHas('status', function ($q) {
                    $q->where('slug', 'pengiriman');
                })->count(),
                'diterima' => Pengiriman::whereHas('status', function ($q) {
                    $q->where('slug', 'diterima');
                })->count(),
            ];
        }
    }

    /**
     * Show the form for editing pengiriman
     */
    public function edit(Pengiriman $pengiriman)
    {
        auth()->user()->can(PermissionEnum::SHIPMENTS_UPDATE->value);

        $pengiriman->load(['donatur', 'jenisQuran', 'status', 'creator', 'wakafItem']);

        // OPTIMIZED: Get approved mushaf requests for address selection with column selection
        $approvedMushafRequests = MushafRequest::approved()
            ->where(function ($query) use ($pengiriman) {
                $query->whereNull('pengiriman_id') // Only those not yet processed
                    ->orWhere('pengiriman_id', $pengiriman->id); // Or the one linked to this pengiriman
            })
            ->select('id', 'no_request', 'nama_lembaga', 'alamat_lengkap', 'nama_pengurus_1', 'whatsapp_pengurus_1')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'no_request' => $request->no_request,
                    'nama_lembaga' => $request->nama_lembaga,
                    'alamat_lengkap' => $request->alamat_lengkap,
                    'nama_pengurus_1' => $request->nama_pengurus_1,
                    'whatsapp_pengurus_1' => $request->whatsapp_pengurus_1,
                    'label' => "{$request->no_request} - {$request->nama_lembaga}",
                    'full_address' => $request->alamat_lengkap,
                ];
            });

        return Inertia::render('Admin/Pengiriman/Edit', [
            'pengiriman' => $pengiriman,
            'statusList' => StatusPengiriman::active()->select('id', 'nama', 'slug', 'warna')->get(),
            'jenisQuranList' => JenisQuran::active()->select('id', 'nama_jenis', 'kode_jenis')->get(),
            'approvedMushafRequests' => $approvedMushafRequests,
        ]);
    }

    /**
     * Update pengiriman
     */
    public function update(Request $request, Pengiriman $pengiriman)
    {
        auth()->user()->can(PermissionEnum::SHIPMENTS_UPDATE->value);

        $request->validate([
            'status_id' => ['required', 'exists:status_pengiriman,id'],
            'alamat_tujuan' => ['nullable', 'string', 'max:500'],
            'nama_penerima' => ['nullable', 'string', 'max:255'],
            'no_hp_penerima' => ['nullable', 'string', 'max:20'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            \DB::beginTransaction();

            // Lock the pengiriman row to prevent concurrent status updates
            $lockedPengiriman = Pengiriman::where('id', $pengiriman->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedPengiriman) {
                throw new \Exception('Pengiriman tidak ditemukan');
            }

            $oldStatus = $lockedPengiriman->status_id;

            // Validate status transition ONLY if status actually changes
            // (update() also handles address/recipient edits with unchanged status)
            if ((int) $oldStatus !== (int) $request->status_id) {
                $validTransition = $this->validateStatusTransition($oldStatus, $request->status_id);
                if (! $validTransition['valid']) {
                    throw new \Exception($validTransition['message']);
                }
            }

            // Update pengiriman
            $lockedPengiriman->update($request->only([
                'status_id', 'alamat_tujuan', 'nama_penerima',
                'no_hp_penerima', 'catatan',
            ]));

            // Log status change if changed
            if ($lockedPengiriman->wasChanged('status_id')) {
                StatusHistory::create([
                    'pengiriman_id' => $lockedPengiriman->id,
                    'status_from' => $oldStatus,
                    'status_to' => $lockedPengiriman->status_id,
                    'catatan' => $request->catatan,
                    'created_by' => auth()->id(),
                ]);
            }

            \DB::commit();

            return redirect()->route('admin.pengiriman.index')
                ->with('success', "Pengiriman {$lockedPengiriman->no_resi} berhasil diupdate.");

        } catch (\Exception $e) {
            \DB::rollback();

            return back()->withErrors([
                'error' => 'Gagal mengupdate pengiriman: '.$e->getMessage(),
            ])->withInput();
        }
    }

    /**
     * Bulk update status
     */
    public function bulkUpdateStatus(Request $request)
    {
        auth()->user()->can(PermissionEnum::SHIPMENTS_BULK_UPDATE->value);

        // Allow both single file and array of files
        $validator = \Validator::make($request->all(), [
            'pengiriman_ids' => ['required', 'array'],
            'pengiriman_ids.*' => ['exists:pengiriman,id'],
            'status_id' => ['required', 'exists:status_pengiriman,id'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'lokasi' => ['nullable', 'string', 'max:255'],
        ]);

        // Add conditional validation for dokumentasi
        if ($request->has('dokumentasi')) {
            if (is_array($request->file('dokumentasi'))) {
                // Multiple files validation
                $validator->addRules([
                    'dokumentasi' => ['array'],
                    'dokumentasi.*' => ['file', 'image', 'max:10240'],
                ]);
            } else {
                // Single file validation
                $validator->addRules([
                    'dokumentasi' => ['file', 'image', 'max:10240'],
                ]);
            }
        }

        $validator->validate();

        try {
            \DB::beginTransaction();

            $updated = 0;
            $skipped = 0;
            $pengirimanList = Pengiriman::whereIn('id', $request->pengiriman_ids)
                ->lockForUpdate()
                ->get();

            // Handle multiple file uploads (from camera or file input)
            $uploadedFiles = [];
            if ($request->hasFile('dokumentasi')) {
                \Log::info('Has dokumentasi files');
                $files = is_array($request->file('dokumentasi'))
                    ? $request->file('dokumentasi')
                    : [$request->file('dokumentasi')];

                \Log::info('Files to process', ['count' => count($files)]);

                foreach ($files as $index => $file) {
                    if ($file && $file->isValid()) {
                        // Sanitize filename for security
                        $originalName = $file->getClientOriginalName();
                        $safeBaseName = \Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
                        $extension = $file->getClientOriginalExtension();

                        // Validate file extension
                        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        if (! in_array(strtolower($extension), $allowedExtensions)) {
                            throw new \Exception('File type not allowed. Only image files are allowed.');
                        }

                        // Validate MIME type
                        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        if (! in_array($file->getMimeType(), $allowedMimes)) {
                            throw new \Exception('Invalid file type detected.');
                        }

                        $filename = 'bulk_'.time().'_'.$index.'_'.$safeBaseName.'.'.$extension;
                        $path = $file->storeAs('dokumentasi/bulk', $filename, 'public');

                        $uploadedFiles[] = $path;
                    }
                }
            }

            foreach ($pengirimanList as $pengiriman) {
                $oldStatus = $pengiriman->status_id;

                // Validate status transition — skip illegal transitions (backwards, final, same)
                $validTransition = $this->validateStatusTransition($oldStatus, $request->status_id);
                if (! $validTransition['valid']) {
                    \Log::info('Bulk update skipped invalid transition', [
                        'pengiriman_id' => $pengiriman->id,
                        'no_resi' => $pengiriman->no_resi,
                        'status_from' => $oldStatus,
                        'status_to' => $request->status_id,
                        'reason' => $validTransition['message'],
                    ]);
                    $skipped++;

                    continue;
                }

                // Update status
                $pengiriman->update(['status_id' => $request->status_id]);

                // Log status change in StatusHistory
                StatusHistory::create([
                    'pengiriman_id' => $pengiriman->id,
                    'status_from' => $oldStatus,
                    'status_to' => $request->status_id,
                    'catatan' => $request->catatan ?? 'Bulk update status',
                    'created_by' => auth()->id(),
                ]);

                // Create tracking history for the public tracking page
                TrackingHistory::create([
                    'pengiriman_id' => $pengiriman->id,
                    'status_id' => $request->status_id,
                    'user_id' => auth()->id(),
                    'tanggal_update' => now(),
                    'lokasi' => $request->lokasi,
                    'keterangan' => $request->catatan ?? 'Bulk update status',
                    'foto_dokumentasi' => ! empty($uploadedFiles) ? $uploadedFiles : null,
                ]);

                $updated++;
            }

            \DB::commit();

            // Return JSON response for AJAX requests
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "{$updated} pengiriman berhasil diupdate statusnya."
                        .($skipped > 0 ? " {$skipped} dilewati (transisi tidak valid)." : ''),
                    'data' => [
                        'updated_count' => $updated,
                        'skipped_count' => $skipped,
                        'files_uploaded' => count($uploadedFiles),
                        'file_paths' => $uploadedFiles,
                    ],
                ]);
            }

            return back()->with('success', "{$updated} pengiriman berhasil diupdate statusnya."
                .($skipped > 0 ? " {$skipped} dilewati (transisi tidak valid)." : ''));

        } catch (\Exception $e) {
            \DB::rollback();

            \Log::error('Bulk update status failed: '.$e->getMessage(), [
                'request' => $request->except(['dokumentasi']),
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal bulk update: '.$e->getMessage(),
                ], 500);
            }

            return back()->withErrors([
                'error' => 'Gagal bulk update: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Bulk set alamat tujuan
     */
    public function bulkSetAlamat(Request $request)
    {
        auth()->user()->can(PermissionEnum::SHIPMENTS_UPDATE->value);

        $request->validate([
            'pengiriman_ids' => ['required', 'array'],
            'pengiriman_ids.*' => ['exists:pengiriman,id'],
            'alamat_tujuan' => ['required', 'string', 'max:500'],
            'nama_penerima' => ['nullable', 'string', 'max:255'],
            'no_hp_penerima' => ['nullable', 'string', 'max:20'],
            'nama_lembaga' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $updated = Pengiriman::whereIn('id', $request->pengiriman_ids)
                ->update([
                    'alamat_tujuan' => $request->alamat_tujuan,
                    'nama_penerima' => $request->nama_penerima,
                    'no_hp_penerima' => $request->no_hp_penerima,
                    'nama_lembaga' => $request->nama_lembaga,
                ]);

            return back()->with('success', "{$updated} pengiriman berhasil diset alamat tujuannya.");

        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Gagal set alamat: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Show tracking detail
     */
    public function show(Pengiriman $pengiriman)
    {
        auth()->user()->can(PermissionEnum::SHIPMENTS_READ->value);

        $pengiriman->load([
            'donatur', 'wakafItem', 'jenisQuran', 'status', 'creator',
            'statusHistory.statusFrom', 'statusHistory.statusTo', 'statusHistory.creator',
        ]);

        return Inertia::render('Admin/Pengiriman/Show', [
            'pengiriman' => $pengiriman,
            'statusHistory' => $pengiriman->statusHistory()
                ->with(['statusFrom', 'statusTo', 'creator'])
                ->orderBy('created_at', 'desc')
                ->get(),
        ]);
    }

    /**
     * Show QR Code generation page (from root PengirimanController)
     */
    public function generateQRPage(Request $request)
    {
        auth()->user()->can(PermissionEnum::QR_GENERATE->value);

        // Show pengiriman with all active non-final statuses (from pemesanan to pengiriman)
        $activeStatusIds = array_values(StatusPengirimanCache::getIdsBySlug([
            'pemesanan',
            'produksi',
            'kedatangan',
            'packing',
            'selesai-packing',
            'pengiriman',
        ]));

        if (empty($activeStatusIds)) {
            // Fallback to IDs 1-6 if cache lookup fails
            $activeStatusIds = [1, 2, 3, 4, 5, 6];
        }

        $query = Pengiriman::with([
            'donatur:id,nama_donatur,kode_donatur,no_hp',
            'wakafItem:id,pengiriman_id,wakif_name,doa_request,relationship_to_donatur',
            'mushafRequest:id,pengiriman_id,nama_lembaga,kategori_lembaga',
            'jenisQuran:id,nama_jenis,kode_jenis',
            'status:id,nama,slug,warna',
            'creator:id,name',
        ])
            ->whereIn('status_id', $activeStatusIds) // Filter by all active non-final statuses
            ->select('id', 'no_resi', 'nama_penerima', 'nama_lembaga', 'no_hp_penerima', 'alamat_tujuan', 'donatur_id', 'wakaf_item_id', 'jenis_quran_id', 'status_id', 'jumlah_quran', 'created_at', 'qr_code_path', 'qr_code_data');

        // Search functionality
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('no_resi', 'like', '%'.$request->search.'%')
                    ->orWhere('nama_penerima', 'like', '%'.$request->search.'%')
                    ->orWhere('alamat_tujuan', 'like', '%'.$request->search.'%')
                    ->orWhereHas('donatur', function ($wq) use ($request) {
                        $wq->where('nama_donatur', 'like', '%'.$request->search.'%')
                            ->orWhere('kode_donatur', 'like', '%'.$request->search.'%');
                    });
            });
        }

        // Date filtering
        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Filter by QR status
        if ($request->qr_status) {
            if ($request->qr_status === 'has_qr') {
                $query->whereNotNull('qr_code_data');
            } elseif ($request->qr_status === 'no_qr') {
                $query->whereNull('qr_code_data');
            }
        }

        // ✅ ENHANCEMENT: Order first (for row number calculation)
        $query->orderBy('no_resi', 'desc');

        // ✅ ENHANCEMENT: Handle row number filtering for 500 rows pagination
        $perPage = 500;
        $page = 1;

        if ($request->filled('number_from') && $request->filled('number_to')) {
            // Both from and to specified
            $numberFrom = max(1, (int) $request->number_from);
            $numberTo = max($numberFrom, (int) $request->number_to);

            // Calculate which page the "from" number is on
            $page = (int) ceil($numberFrom / $perPage);

            // Calculate how many items to skip within the page
            $skipInPage = ($numberFrom - 1) % $perPage;

            // Calculate how many items to take
            $itemsToTake = ($numberTo - $numberFrom + 1);

            // Get the paginated results
            $pengiriman = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();

            // Slice the collection to get only the specified range
            $slicedData = collect($pengiriman->items())->slice($skipInPage, $itemsToTake)->values();

            // Rebuild pagination with sliced data
            $pengiriman = new LengthAwarePaginator(
                $slicedData,
                $pengiriman->total(),
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            // Manually set the 'from' property for correct row numbering
            $pengiriman->from = $numberFrom;
        } elseif ($request->filled('number_from')) {
            // Only from specified - show from that number onwards
            $numberFrom = max(1, (int) $request->number_from);
            $page = (int) ceil($numberFrom / $perPage);
            $skipInPage = ($numberFrom - 1) % $perPage;

            $pengiriman = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();

            $slicedData = collect($pengiriman->items())->slice($skipInPage)->values();

            $pengiriman = new LengthAwarePaginator(
                $slicedData,
                $pengiriman->total(),
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            $pengiriman->from = $numberFrom;
        } elseif ($request->filled('number_to')) {
            // Only to specified - show from beginning to that number
            $numberTo = max(1, (int) $request->number_to);
            $itemsToTake = $numberTo;

            $pengiriman = $query->paginate($perPage)->withQueryString();

            $slicedData = collect($pengiriman->items())->slice(0, $itemsToTake)->values();

            $pengiriman = new LengthAwarePaginator(
                $slicedData,
                $pengiriman->total(),
                $perPage,
                1,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        } else {
            // No row number filter - normal pagination with 500 rows
            $pengiriman = $query->paginate($perPage)->withQueryString();
        }

        // Transform the paginated data
        $pengiriman->getCollection()->transform(function ($item) {
            // Ensure jenis_quran has fallback
            if (! $item->jenisQuran && $item->jenis_quran_id) {
                // Try to load missing jenis_quran
                $jenisQuran = JenisQuran::find($item->jenis_quran_id);
                if ($jenisQuran) {
                    $item->setRelation('jenisQuran', $jenisQuran);
                }
            }

            // Add fallback data if still missing
            if (! $item->jenisQuran) {
                $item->setRelation('jenisQuran', (object) [
                    'id' => null,
                    'nama_jenis' => 'Al-Quran Standar',
                    'kode_jenis' => 'DEFAULT',
                ]);
            }

            // Ensure status has fallback
            if (! $item->status && $item->status_id) {
                $status = StatusPengiriman::find($item->status_id);
                if ($status) {
                    $item->setRelation('status', $status);
                }
            }

            // Map to consistent format for frontend
            return [
                'id' => $item->id,
                'no_resi' => $item->no_resi,
                'nama_penerima' => $item->nama_penerima,
                'nama_lembaga' => $item->mushafRequest?->nama_lembaga ?? $item->nama_lembaga,
                'no_hp_penerima' => $item->no_hp_penerima ?? null,
                'alamat_tujuan' => $item->alamat_tujuan,
                'jumlah_quran' => $item->jumlah_quran,
                'created_at' => $item->created_at,
                'has_qr' => ! empty($item->qr_code_path),
                'qr_url' => $item->qr_code_path ? \Storage::url($item->qr_code_path) : null,
                'qr_data' => $item->qr_code_data,
                'donatur' => $item->donatur ? [
                    'id' => $item->donatur->id,
                    'nama_donatur' => $item->donatur->nama_donatur,
                    'kode_donatur' => $item->donatur->kode_donatur,
                ] : null,
                'wakaf_item' => $item->wakafItem ? [
                    'id' => $item->wakafItem->id,
                    'wakif_name' => $item->wakafItem->wakif_name,
                    'doa_request' => $item->wakafItem->doa_request,
                    'relationship_to_donatur' => $item->wakafItem->relationship_to_donatur,
                ] : null,
                'jenisQuran' => [
                    'id' => $item->jenisQuran->id,
                    'nama_jenis' => $item->jenisQuran->nama_jenis,
                    'kode_jenis' => $item->jenisQuran->kode_jenis,
                ],
                'status' => $item->status ? [
                    'id' => $item->status->id,
                    'nama' => $item->status->nama,
                    'slug' => $item->status->slug,
                    'warna' => $item->status->warna,
                ] : [
                    'id' => 1,
                    'nama' => 'Pending',
                    'slug' => 'pending',
                    'warna' => 'gray',
                ],
            ];
        });

        // Get counts for all active non-final statuses
        $totalCount = Pengiriman::whereIn('status_id', $activeStatusIds)->count();
        $totalWithQR = Pengiriman::whereIn('status_id', $activeStatusIds)
            ->whereNotNull('qr_code_data')
            ->count();
        $totalPendingQR = Pengiriman::whereIn('status_id', $activeStatusIds)
            ->whereNull('qr_code_data')
            ->count();

        return Inertia::render('Admin/Pengiriman/GenerateQR', [
            'pengiriman' => $pengiriman,
            'filters' => $request->only(['search', 'start_date', 'end_date', 'qr_status', 'number_from', 'number_to']),
            'statusList' => StatusPengiriman::active()->select('id', 'nama', 'slug', 'warna')->get(),
            'jenisQuranList' => JenisQuran::active()->select('id', 'nama_jenis', 'kode_jenis')->get(),
            'stats' => [
                'total_pengiriman' => $totalCount,
                'pending_qr' => $totalPendingQR,
                'has_qr' => $totalWithQR,
                'status_filter' => 'Semua Status Aktif',
            ],
        ]);
    }

    /**
     * Show QR Status Scanner page
     */
    public function scanStatus()
    {
        auth()->user()->can(PermissionEnum::QR_SCAN->value);

        return Inertia::render('Admin/Pengiriman/ScanStatus', [
            'statusList' => StatusPengiriman::active()
                ->ordered()
                ->get(['id', 'nama', 'slug', 'deskripsi', 'warna', 'urutan', 'is_final'])
                ->map(function ($status) {
                    return [
                        'id' => $status->id,
                        'nama' => $status->nama,
                        'slug' => $status->slug,
                        'deskripsi' => $status->deskripsi,
                        'warna' => $status->warna,
                        'urutan' => $status->urutan,
                        'is_final' => $status->is_final,
                        'icon' => $status->icon, // This will use the getIconAttribute method
                        'badge_class' => $status->badge_class, // This will use the getBadgeClassAttribute method
                    ];
                }),
        ]);
    }

    /**
     * Get pengiriman info and valid next statuses for QR scanner
     */
    public function getPengirimanStatusInfo($noResi)
    {
        auth()->user()->can(PermissionEnum::SHIPMENTS_TRACK->value);

        try {
            $pengiriman = Pengiriman::where('no_resi', $noResi)
                ->with(['donatur', 'status', 'jenisQuran', 'wakafItem'])
                ->first();

            if (! $pengiriman) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengiriman dengan resi '.$noResi.' tidak ditemukan',
                ], 404);
            }

            // Get next possible statuses
            $currentStatus = $pengiriman->status;
            if (! $currentStatus) {
                return response()->json([
                    'success' => false,
                    'message' => 'Status pengiriman tidak ditemukan',
                ], 404);
            }

            try {
                $nextStatuses = $this->getNextPossibleStatuses($currentStatus->id);
            } catch (ModelNotFoundException $e) {
                \Log::error('Status not found in getPengirimanStatusInfo', [
                    'no_resi' => $noResi,
                    'status_id' => $currentStatus->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Status pengiriman tidak valid',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'pengiriman' => [
                    'id' => $pengiriman->id,
                    'no_resi' => $pengiriman->no_resi,
                    'donatur' => $pengiriman->donatur->nama_donatur ?? 'Unknown',
                    'wakif' => $pengiriman->wakafItem->wakif_name ?? $pengiriman->donatur->nama_donatur ?? 'Unknown',
                    'jenis_quran' => $pengiriman->jenisQuran->nama_jenis ?? 'Unknown',
                    'jumlah_quran' => $pengiriman->jumlah_quran ?? 0,
                    'current_status' => [
                        'id' => $currentStatus->id,
                        'nama' => $currentStatus->nama,
                        'slug' => $currentStatus->slug,
                        'warna' => $currentStatus->warna,
                        'icon' => $currentStatus->icon,
                        'urutan' => $currentStatus->urutan,
                        'is_final' => $currentStatus->is_final,
                    ],
                ],
                'validStatuses' => $nextStatuses,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getPengirimanStatusInfo: '.$e->getMessage(), [
                'no_resi' => $noResi,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data pengiriman',
            ], 500);
        }
    }

    /**
     * Update status by QR scan - ENHANCED VERSION WITH FILE UPLOAD
     */
    public function updateStatusByScan(ScanStatusRequest $request)
    {
        auth()->user()->can(PermissionEnum::SHIPMENTS_UPDATE_STATUS->value);

        try {
            \Log::info('=== SCAN STATUS UPDATE REQUEST ===');
            \Log::info('No resi from request', ['no_resi' => $request->no_resi]);

            \DB::beginTransaction();

            // Get validated no_resi (prioritizes QR data if available)
            $noResi = $request->getValidatedNoResi();

            // Find pengiriman with row lock to prevent concurrent status updates
            $pengiriman = Pengiriman::where('no_resi', $noResi)
                ->lockForUpdate()
                ->with(['donatur', 'status', 'jenisQuran'])
                ->first();

            if (! $pengiriman) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengiriman dengan resi '.$noResi.' tidak ditemukan',
                ], 404);
            }

            $oldStatus = $pengiriman->status_id;

            // Validate status transition
            $validTransition = $this->validateStatusTransition($oldStatus, $request->status_id);
            if (! $validTransition['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Perubahan status tidak valid: '.$validTransition['message'],
                ], 400);
            }

            // Prepare status history data
            $statusHistoryData = [
                'pengiriman_id' => $pengiriman->id,
                'status_from' => $oldStatus,
                'status_to' => $request->status_id,
                'catatan' => $request->catatan ?? 'Update via QR Scan',
                'lokasi' => $request->lokasi,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'created_by' => auth()->id(),
            ];

            // Handle file uploads
            $uploadedFiles = [];
            if ($request->hasFile('dokumentasi')) {
                foreach ($request->file('dokumentasi') as $index => $file) {
                    if ($file && $file->isValid()) {
                        // Generate filename using pengiriman no_resi format
                        $filename = time().'_'.$index.'.'.$file->getClientOriginalExtension();

                        // Store in directory structure: dokumentasi/{no_resi}/{filename}
                        $directory = "dokumentasi/{$pengiriman->no_resi}";
                        $path = $file->storeAs($directory, $filename, 'public');

                        // Add to uploaded files array (simple path format)
                        $uploadedFiles[] = $path;
                    }
                }
            }

            // Add uploaded files to status history
            if (! empty($uploadedFiles)) {
                $statusHistoryData['dokumentasi'] = json_encode($uploadedFiles);
            }

            // Update pengiriman status
            $pengiriman->update(['status_id' => $request->status_id]);

            // Create status history record
            StatusHistory::create($statusHistoryData);

            // Create tracking history record (for public tracking page)
            TrackingHistory::create([
                'pengiriman_id' => $pengiriman->id,
                'status_id' => $request->status_id,
                'user_id' => auth()->id(),
                'tanggal_update' => now(),
                'lokasi' => $request->lokasi,
                'keterangan' => $request->catatan ?? 'Update via QR Scan',
                'foto_dokumentasi' => ! empty($uploadedFiles) ? $uploadedFiles : null,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);

            // Log activity
            \Log::info('Status updated via QR scan with documentation', [
                'no_resi' => $pengiriman->no_resi,
                'old_status' => $oldStatus,
                'new_status' => $request->status_id,
                'qr_verified' => $request->isQRVerified(),
                'lokasi' => $request->lokasi,
                'files_uploaded' => count($uploadedFiles),
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name,
            ]);

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Status pengiriman {$pengiriman->no_resi} berhasil diupdate",
                'data' => [
                    'no_resi' => $pengiriman->no_resi,
                    'old_status' => $oldStatus,
                    'new_status' => $request->status_id,
                    'qr_verified' => $request->isQRVerified(),
                    'lokasi' => $request->lokasi,
                    'files_uploaded' => count($uploadedFiles),
                    'pengiriman' => [
                        'id' => $pengiriman->id,
                        'no_resi' => $pengiriman->no_resi,
                        'donatur' => $pengiriman->donatur->nama_donatur ?? 'Unknown',
                        'jenis_quran' => $pengiriman->jenisQuran->nama_jenis ?? 'Unknown',
                        'jumlah_quran' => $pengiriman->jumlah_quran,
                        'status' => [
                            'id' => $pengiriman->status->id,
                            'nama' => $pengiriman->status->nama,
                            'warna' => $pengiriman->status->warna ?? 'gray',
                        ],
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            \DB::rollback();

            \Log::error('Update status by scan failed: '.$e->getMessage(), [
                'request' => $request->except(['dokumentasi']), // Exclude files from log
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat update status. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * Validate status transition for scan status updates - ENHANCED VERSION
     */
    private function validateStatusTransition($currentStatusId, $newStatusId)
    {
        $newStatus = StatusPengiriman::findOrFail($newStatusId);
        $currentStatus = StatusPengiriman::findOrFail($currentStatusId);

        // Always allow changing to 'Batal' status
        if ($newStatus->slug === 'batal') {
            return [
                'valid' => true,
                'message' => 'Status dapat dibatalkan kapan saja',
            ];
        }

        // If status is the same, no need to update
        if ($currentStatusId == $newStatusId) {
            return [
                'valid' => false,
                'message' => 'Status sudah sama, tidak perlu diupdate',
            ];
        }

        // If old status is final, it cannot be changed
        if ($currentStatus->is_final && $currentStatus->slug !== 'batal') {
            return [
                'valid' => false,
                'message' => 'Status sudah final, tidak bisa diubah',
            ];
        }

        // SPECIAL CASE: Allow 'Diterima' from 'Pengiriman' or 'Dokumentasi'
        if ($newStatus->slug === 'diterima' &&
            in_array($currentStatus->slug, ['pengiriman', 'selesai-packing'])) {
            return [
                'valid' => true,
                'message' => 'Perubahan ke status diterima valid dari pengiriman/dokumentasi',
            ];
        }

        // Normal progression check - don't allow backwards (except special cases above)
        if ($newStatus->urutan < $currentStatus->urutan && $newStatus->slug !== 'batal') {
            return [
                'valid' => false,
                'message' => 'Tidak dapat mengubah status ke tahap sebelumnya',
            ];
        }

        return [
            'valid' => true,
            'message' => 'Perubahan status valid',
        ];
    }

    /**
     * Enhanced unified QR scan processing method
     */
    public function processScanQR(ScanStatusRequest $request)
    {
        auth()->user()->can(PermissionEnum::SHIPMENTS_UPDATE_STATUS->value);

        // Add JSON response header immediately
        if (! $request->expectsJson()) {
            $request->headers->set('Accept', 'application/json');
        }

        try {
            \Log::info('processScanQR started', [
                'no_resi' => $request->no_resi,
                'status_id' => $request->status_id,
                'user_id' => auth()->id(),
            ]);

            \DB::beginTransaction();

            // Get validated no_resi (prioritizes QR data if available)
            $noResi = $request->getValidatedNoResi();

            // Find pengiriman (locked to prevent concurrent status updates)
            $pengiriman = Pengiriman::where('no_resi', $noResi)
                ->lockForUpdate()
                ->with(['donatur', 'status', 'jenisQuran'])
                ->first();

            if (! $pengiriman) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengiriman dengan resi '.$noResi.' tidak ditemukan',
                ], 404);
            }

            $oldStatus = $pengiriman->status_id;
            $newStatusId = $request->status_id;

            // Validate status transition
            $validTransition = $this->validateStatusTransition($oldStatus, $newStatusId);
            if (! $validTransition['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Perubahan status tidak valid: '.$validTransition['message'],
                ], 400);
            }

            // Get new status info
            $newStatus = StatusPengiriman::findOrFail($newStatusId);

            // Handle file uploads - FIXED: Use same format as working updateStatusWithDocs
            $dokFiles = [];
            if ($request->hasFile('dokumentasi')) {
                \Log::info('Processing files for QR scan...');
                foreach ($request->file('dokumentasi') as $index => $file) {
                    if ($file && $file->isValid()) {
                        // Use same filename pattern as working method
                        $filename = time().'_'.$index.'.'.$file->getClientOriginalExtension();

                        // Store in same directory structure as working method
                        $path = $file->storeAs('dokumentasi/'.$pengiriman->no_resi, $filename, 'public');

                        // Add to uploaded files array (simple path format like working method)
                        $dokFiles[] = $path;
                        \Log::info('File saved for QR scan', ['path' => $path]);
                    } else {
                        \Log::warning('Invalid file at index for QR scan', ['index' => $index]);
                    }
                }
            }

            \Log::info('Final dokFiles array for QR scan', ['dokFiles' => $dokFiles]);

            // Update pengiriman status
            $pengiriman->update(['status_id' => $newStatusId]);

            // Create tracking history record - FIXED: Use same format as working method
            TrackingHistory::create([
                'pengiriman_id' => $pengiriman->id,
                'status_id' => $newStatusId,
                'user_id' => auth()->id(),
                'tanggal_update' => now(),
                'lokasi' => $request->lokasi,
                'keterangan' => $request->catatan ?? 'Update via QR Scan',
                'foto_dokumentasi' => $dokFiles, // Use simple array format like working method
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);

            // Create status history - FIXED: Add missing StatusHistory creation
            StatusHistory::create([
                'pengiriman_id' => $pengiriman->id,
                'status_from' => $oldStatus,
                'status_to' => $newStatusId,
                'catatan' => $request->catatan ?? 'Update via QR Scan',
                'created_by' => auth()->id(),
            ]);

            // Log activity
            \Log::info('Status updated via unified QR scan processor', [
                'no_resi' => $pengiriman->no_resi,
                'old_status' => $oldStatus,
                'new_status' => $newStatusId,
                'qr_verified' => $request->isQRVerified(),
                'lokasi' => $request->lokasi,
                'files_uploaded' => count($dokFiles),
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name,
            ]);

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Status pengiriman {$pengiriman->no_resi} berhasil diupdate ke {$newStatus->nama}",
                'data' => [
                    'no_resi' => $pengiriman->no_resi,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatusId,
                    'lokasi' => $request->lokasi,
                    'qr_verified' => $request->isQRVerified(),
                    'files_uploaded' => count($dokFiles),
                    'file_paths' => $dokFiles, // Return simple paths for debugging
                    'pengiriman' => [
                        'id' => $pengiriman->id,
                        'no_resi' => $pengiriman->no_resi,
                        'donatur' => $pengiriman->donatur->nama_donatur ?? 'Unknown',
                        'jenis_quran' => $pengiriman->jenisQuran->nama_jenis ?? 'Unknown',
                        'jumlah_quran' => $pengiriman->jumlah_quran,
                        'status' => [
                            'id' => $newStatus->id,
                            'nama' => $newStatus->nama,
                            'warna' => $newStatus->warna ?? 'gray',
                            'icon' => $newStatus->icon ?? '📦',
                        ],
                    ],
                ],
            ]);

        } catch (ValidationException $e) {
            \DB::rollback();

            \Log::warning('Validation failed in processScanQR', [
                'errors' => $e->errors(),
                'request' => $request->except(['dokumentasi']),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            \DB::rollback();

            \Log::error('Unified QR scan processor failed: '.$e->getMessage(), [
                'request' => $request->except(['dokumentasi']), // Exclude files from log
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            // Return more detailed error for debugging
            $errorMessage = 'Terjadi kesalahan sistem saat update status.';

            if (app()->environment('local')) {
                $errorMessage .= ' Debug: '.$e->getMessage().' (Line: '.$e->getLine().')';
            }

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'debug' => app()->environment('local') ? [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => basename($e->getFile()),
                    'class' => get_class($e),
                ] : null,
            ], 500);
        }
    }

    /**
     * Get next possible statuses for status transition - FIXED VERSION
     */
    private function getNextPossibleStatuses($currentStatusId)
    {
        $currentStatus = StatusPengiriman::findOrFail($currentStatusId);

        // If status is already final (except 'batal'), no next status
        if ($currentStatus->is_final && $currentStatus->slug !== 'batal') {
            return collect([
                [
                    'id' => null,
                    'nama' => 'Status Sudah Final',
                    'slug' => 'final',
                    'deskripsi' => 'Pengiriman telah selesai dan tidak dapat diubah lagi',
                    'warna' => 'gray',
                    'urutan' => 999,
                    'icon' => '🏁',
                    'is_final' => true,
                    'badge_class' => 'bg-gray-100 text-gray-800',
                    'disabled' => true,
                ],
            ]);
        }

        // FIXED: Get all active statuses that can be progressed to
        $nextStatuses = StatusPengiriman::where('is_active', true)
            ->where('id', '!=', $currentStatusId) // Exclude current status
            ->where(function ($query) use ($currentStatus) {
                // Allow progression to higher order statuses
                $query->where('urutan', '>', $currentStatus->urutan)
                      // Always allow 'Batal' status
                    ->orWhere('slug', 'batal')
                      // SPECIAL CASE: Allow 'Diterima' from 'Pengiriman' or 'Dokumentasi'
                    ->orWhere(function ($subQuery) use ($currentStatus) {
                        // Allow 'diterima' status if current status is 'pengiriman' or 'dokumentasi'
                        if (in_array($currentStatus->slug, ['pengiriman', 'selesai-packing'])) {
                            $subQuery->where('slug', 'diterima');
                        }
                    });
            })
            ->orderBy('urutan')
            ->get();

        // Map data with safe icon and badge class handling
        return $nextStatuses->map(function ($status) {
            // Safe icon handling - use database value or fallback
            $icon = $status->getAttributeValue('icon') ?: match ($status->slug) {
                'pending' => '⏳',
                'dikemas' => '📦',
                'dikirim' => '🚚',
                'diterima' => '✅',
                'batal' => '❌',
                'pemesanan' => '📝',
                'produksi' => '🏭',
                'kedatangan' => '📦',
                'packing' => '🎁',
                'selesai-packing' => '✅',
                'pengiriman' => '🚚',
                default => '📋'
            };

            // Safe badge class handling - use warna or fallback
            $badgeClass = match ($status->warna ?? 'gray') {
                'gray' => 'bg-gray-100 text-gray-800',
                'blue' => 'bg-blue-100 text-blue-800',
                'yellow' => 'bg-yellow-100 text-yellow-800',
                'green' => 'bg-green-100 text-green-800',
                'red' => 'bg-red-100 text-red-800',
                'purple' => 'bg-purple-100 text-purple-800',
                'indigo' => 'bg-indigo-100 text-indigo-800',
                'cyan' => 'bg-cyan-100 text-cyan-800',
                'teal' => 'bg-teal-100 text-teal-800',
                default => 'bg-gray-100 text-gray-800'
            };

            return [
                'id' => $status->id,
                'nama' => $status->nama,
                'slug' => $status->slug,
                'deskripsi' => $status->deskripsi,
                'warna' => $status->warna,
                'urutan' => $status->urutan,
                'icon' => $icon,
                'is_final' => $status->is_final,
                'badge_class' => $badgeClass,
            ];
        });
    }

    /**
     * Bulk generate QR codes for selected pengiriman
     */
    public function bulkGenerateQR(Request $request)
    {
        auth()->user()->can(PermissionEnum::WAREHOUSE_QR_BULK_GENERATE->value);

        $request->validate([
            'pengiriman_ids' => ['required', 'array'],
            'pengiriman_ids.*' => ['exists:pengiriman,id'],
        ]);

        try {
            \DB::beginTransaction();

            $results = [];
            $successCount = 0;
            $errorCount = 0;
            $skippedCount = 0;

            $pengirimanList = Pengiriman::with(['donatur', 'jenisQuran'])
                ->whereIn('id', $request->pengiriman_ids)
                ->get();

            foreach ($pengirimanList as $pengiriman) {
                try {
                    // Check if QR already exists
                    if ($pengiriman->qr_code_path && \Storage::exists($pengiriman->qr_code_path)) {
                        $results[] = [
                            'id' => $pengiriman->id,
                            'no_resi' => $pengiriman->no_resi,
                            'status' => 'skipped',
                            'message' => 'QR Code sudah ada',
                        ];
                        $skippedCount++;

                        continue;
                    }

                    // Generate QR data
                    $qrController = new QRCodeController;

                    // Create a fake request for the QR controller
                    $fakeRequest = new Request;
                    $fakeRequest->headers->set('Accept', 'application/json');
                    app()->instance('request', $fakeRequest);

                    $result = $qrController->generate($pengiriman);

                    if ($result instanceof JsonResponse) {
                        $data = $result->getData(true);
                        if ($data['success'] ?? false) {
                            $results[] = [
                                'id' => $pengiriman->id,
                                'no_resi' => $pengiriman->no_resi,
                                'status' => 'success',
                                'message' => 'QR Code berhasil dibuat',
                                'qr_url' => $data['data']['qr_url'] ?? null,
                            ];
                            $successCount++;
                        } else {
                            throw new \Exception($data['message'] ?? 'QR generation failed');
                        }
                    } else {
                        $results[] = [
                            'id' => $pengiriman->id,
                            'no_resi' => $pengiriman->no_resi,
                            'status' => 'success',
                            'message' => 'QR Code berhasil dibuat',
                        ];
                        $successCount++;
                    }

                } catch (\Exception $e) {
                    $results[] = [
                        'id' => $pengiriman->id,
                        'no_resi' => $pengiriman->no_resi,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                    $errorCount++;
                }
            }

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Bulk QR generation completed. Success: {$successCount}, Errors: {$errorCount}, Skipped: {$skippedCount}",
                'summary' => [
                    'total' => count($pengirimanList),
                    'success' => $successCount,
                    'errors' => $errorCount,
                    'skipped' => $skippedCount,
                ],
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            \DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Bulk QR generation failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
