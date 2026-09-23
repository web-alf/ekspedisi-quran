<?php

namespace App\Http\Controllers\Admin;

use App\Exports\MushafRequestExport;
use App\Http\Controllers\Controller;
use App\Models\Donatur;
use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Services\Cache\DashboardCacheService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class MushafRequestController extends Controller
{
    protected $dashboardCache;

    public function __construct(
        DashboardCacheService $dashboardCache
    ) {
        $this->dashboardCache = $dashboardCache;
        $this->authorizeResource(MushafRequest::class, 'mushafRequest');
    }

    /**
     * Display a listing of mushaf requests
     */
    public function index(Request $request)
    {
        // OPTIMIZED: Use constrained eager loading to prevent N+1 queries
        $query = MushafRequest::with([
            'reviewer:id,name',
            'pengiriman:id,mushaf_request_id,no_resi,status_id',
        ])
            ->orderBy('created_at', 'desc');

        // Search functionality
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('no_request', 'like', '%'.$request->search.'%')
                    ->orWhere('nama_lembaga', 'like', '%'.$request->search.'%')
                    ->orWhere('nama_pengurus_1', 'like', '%'.$request->search.'%')
                    ->orWhere('nama_pengurus_2', 'like', '%'.$request->search.'%')
                    ->orWhere('alamat_lengkap', 'like', '%'.$request->search.'%')
                    ->orWhere('provinsi', 'like', '%'.$request->search.'%')
                    ->orWhere('kota_kabupaten', 'like', '%'.$request->search.'%')
                    ->orWhere('kecamatan', 'like', '%'.$request->search.'%')
                    ->orWhere('kelurahan_desa', 'like', '%'.$request->search.'%');
            });
        }

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('created_at', [
                $request->start_date.' 00:00:00',
                $request->end_date.' 23:59:59',
            ]);
        } elseif ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        } elseif ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $mushafRequests = $query->paginate(20)->withQueryString();

        // Get map data for distribution visualization
        // ✅ FIX: Use same data source as landing page - only show completed requests
        // This ensures admin and public maps are synchronized
        $mapData = MushafRequest::where('status', 'completed')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'nama_lembaga', 'provinsi', 'kota_kabupaten', 'latitude', 'longitude', 'status',
                'jumlah_mushaf', 'jumlah_iqra', 'jumlah_mushaf_approved', 'jumlah_iqra_approved', 'kategori_lembaga')
            ->get()
            ->map(function ($item) {
                // Use approved quantities with fallback to requested
                $approvedMushaf = $item->jumlah_mushaf_approved ?? $item->jumlah_mushaf;
                $approvedIqra = $item->jumlah_iqra_approved ?? $item->jumlah_iqra;

                return [
                    'id' => $item->id,
                    'nama_lembaga' => $item->nama_lembaga,
                    'provinsi' => $item->provinsi,
                    'kota_kabupaten' => $item->kota_kabupaten,
                    'lat' => (float) $item->latitude,
                    'lng' => (float) $item->longitude,
                    'status' => $item->status,
                    'jumlah_mushaf' => $approvedMushaf + $approvedIqra,
                    'kategori' => $item->kategori_lembaga,
                    'nama_penerima' => $item->nama_lembaga, // Add for marker grouping
                ];
            });

        return Inertia::render('Admin/MushafRequest/Index', [
            'mushafRequests' => $mushafRequests,
            'filters' => $request->only(['search', 'status', 'start_date', 'end_date']),
            'mapData' => $mapData,
            'stats' => [
                'total' => MushafRequest::count(),
                'pending' => MushafRequest::pending()->count(),
                'reviewed' => MushafRequest::where('status', 'reviewed')->count(),
                'approved' => MushafRequest::approved()->count(),
                'rejected' => MushafRequest::rejected()->count(),
                'processed' => MushafRequest::processed()->count(),
                'completed' => MushafRequest::completed()->count(),
            ],
        ]);
    }

    /**
     * Show the specified mushaf request
     */
    public function show(MushafRequest $mushafRequest)
    {
        $mushafRequest->load(['reviewer', 'pengiriman.donatur', 'pengiriman.jenisQuran']);

        // Ensure file URLs are available
        $mushafRequest->foto_santri_url = $mushafRequest->foto_santri_path ? asset('storage/'.$mushafRequest->foto_santri_path) : null;
        $mushafRequest->foto_lembaga_url = $mushafRequest->foto_lembaga_path ? asset('storage/'.$mushafRequest->foto_lembaga_path) : null;
        $mushafRequest->file_nama_santri_url = $mushafRequest->file_nama_santri_path ? asset('storage/'.$mushafRequest->file_nama_santri_path) : null;

        // Get donatur list for processing modal
        $donaturList = Donatur::select('id', 'nama_donatur', 'kode_donatur')
            ->orderBy('nama_donatur')
            ->get();

        return Inertia::render('Admin/MushafRequest/Show', [
            'mushafRequest' => $mushafRequest,
            'donaturList' => $donaturList,
        ]);
    }

    /**
     * Update the status of mushaf request
     */
    public function updateStatus(Request $request, MushafRequest $mushafRequest)
    {
        $this->authorize('update', $mushafRequest);

        $request->validate([
            'status' => ['required', 'in:pending,reviewed,approved,rejected,processed,completed'],
            'catatan_admin' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $oldStatus = $mushafRequest->status;

            switch ($request->status) {
                case 'approved':
                    $result = $mushafRequest->approve(auth()->id(), $request->catatan_admin);
                    $message = "Permintaan {$mushafRequest->no_request} telah disetujui";
                    $notificationMessage = "Permintaan mushaf Anda dengan nomor {$mushafRequest->no_request} telah disetujui.";
                    break;

                case 'rejected':
                    if (empty($request->catatan_admin)) {
                        throw new \Exception('Catatan wajib diisi untuk penolakan');
                    }
                    $result = $mushafRequest->reject(auth()->id(), $request->catatan_admin);
                    $message = "Permintaan {$mushafRequest->no_request} telah ditolak";
                    $notificationMessage = "Permintaan mushaf Anda dengan nomor {$mushafRequest->no_request} telah ditolak. Catatan: {$request->catatan_admin}";
                    break;

                case 'completed':
                    $result = $mushafRequest->update([
                        'status' => 'completed',
                        'catatan_admin' => $request->catatan_admin,
                        'reviewed_by' => auth()->id(),
                    ]);
                    $message = "Permintaan {$mushafRequest->no_request} telah diselesaikan";
                    $notificationMessage = "Permintaan mushaf Anda dengan nomor {$mushafRequest->no_request} telah selesai diproses dan mushaf telah sampai di tujuan.";
                    break;

                default:
                    $result = $mushafRequest->update([
                        'status' => $request->status,
                        'catatan_admin' => $request->catatan_admin,
                        'reviewed_by' => auth()->id(),
                    ]);
                    $message = "Status permintaan {$mushafRequest->no_request} telah diupdate";
                    $notificationMessage = "Status permintaan mushaf Anda dengan nomor {$mushafRequest->no_request} telah diupdate menjadi {$mushafRequest->status_label}.";
            }

            // Refresh model dari database dan reload relationships
            $mushafRequest->refresh();
            $mushafRequest->load(['reviewer', 'pengiriman.donatur', 'pengiriman.jenisQuran']);

            // Note: WhatsApp notification feature removed as per requirements

            // ✅ FIX: Invalidate dashboard cache after status update
            $this->dashboardCache->invalidate(['dashboard', 'stats', 'activities']);

            // Return dengan data yang fresh untuk halaman admin
            return redirect()->route('admin.mushaf-requests.show', $mushafRequest)
                ->with('success', $message);

        } catch (\Exception $e) {
            \Log::error('updateStatus error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'error' => 'Gagal update status: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Process approved mushaf request to pengiriman
     */
    public function processToShipment(Request $request, MushafRequest $mushafRequest)
    {
        $this->authorize('process', $mushafRequest);

        if ($mushafRequest->status !== 'approved') {
            return back()->withErrors(['error' => 'Hanya permintaan yang disetujui yang bisa diproses']);
        }

        // ✅ VALIDATION: Check if approved quantities are set when mushaf was edited
        if ($mushafRequest->has_quantity_change && is_null($mushafRequest->jumlah_mushaf_approved)) {
            return back()->withErrors([
                'error' => 'Jumlah mushaf yang disetujui belum ditentukan. Silakan edit jumlah mushaf terlebih dahulu.',
            ]);
        }

        $request->validate([
            'donatur_id' => ['required', 'exists:donatur,id'],
            'tanggal_wakaf' => ['required', 'date'],
        ]);

        try {
            \DB::beginTransaction();

            // ✅ FIX: Use APPROVED quantities, fallback to requested if not edited
            $approvedMushaf = $mushafRequest->jumlah_mushaf_approved ?? $mushafRequest->jumlah_mushaf;
            $approvedIqra = $mushafRequest->jumlah_iqra_approved ?? $mushafRequest->jumlah_iqra;
            $totalApproved = $approvedMushaf + $approvedIqra;

            // Determine jenis_quran_id based on APPROVED breakdown
            $approvedA5 = $mushafRequest->jumlah_mushaf_a5_approved ?? $mushafRequest->jumlah_mushaf_a5;
            $approvedA6 = $mushafRequest->jumlah_mushaf_a6_approved ?? $mushafRequest->jumlah_mushaf_a6;

            $jenisQuranId = 1; // Default A5
            if ($approvedA6 > 0 && $approvedA6 >= $approvedA5) {
                $jenisQuranId = 2; // A6 dominan
            }

            // FIXED: Find existing pengiriman without address and assign mushaf request to it
            // instead of creating a new pengiriman.
            // Race-safe: lock baris yang dipilih (lockForUpdate) sehingga dua proses
            // paralel tidak bisa memilih pengiriman kosong yang sama lalu saling menimpa.
            $pengiriman = Pengiriman::where('donatur_id', $request->donatur_id)
                ->whereNull('alamat_tujuan') // Find pengiriman without address assigned
                ->whereNull('nama_penerima') // And without recipient assigned
                ->lockForUpdate()
                ->first();

            // Double-check setelah lock diperoleh: request lain mungkin sudah mengklaim
            // pengiriman ini dan mengisi alamat di sela-sela pemilihan baris tadi.
            if ($pengiriman && !empty($pengiriman->fresh(['alamat_tujuan', 'nama_penerima'])->alamat_tujuan)) {
                $pengiriman = null;
            }

            // ✅ FIX: Build catatan with quantity adjustment info
            $originalTotal = $mushafRequest->jumlah_mushaf + $mushafRequest->jumlah_iqra;
            $catatan = "Dari permintaan mushaf: {$mushafRequest->no_request} - {$mushafRequest->nama_lembaga}";
            if ($mushafRequest->has_quantity_change) {
                $catatan .= " (Jumlah disesuaikan dari {$originalTotal} menjadi {$totalApproved})";
            }

            if (! $pengiriman) {
                // If no existing empty pengiriman, create a new one
                $pengiriman = Pengiriman::create([
                    'donatur_id' => $request->donatur_id,
                    'jenis_quran_id' => $jenisQuranId,
                    'jumlah_quran' => $totalApproved, // ✅ FIX: Use approved quantity
                    'tanggal_wakaf' => $request->tanggal_wakaf,
                    'status_id' => \App\Models\StatusPengiriman::getDefaultStatusId(),
                    'alamat_tujuan' => $mushafRequest->alamat_lengkap,
                    'nama_penerima' => $mushafRequest->nama_pengurus_1,
                    'nama_lembaga' => $mushafRequest->nama_lembaga,
                    'no_hp_penerima' => $mushafRequest->whatsapp_pengurus_1,
                    'catatan' => $catatan,
                    'created_by' => auth()->id(),
                ]);
            } else {
                // Update existing pengiriman with mushaf request details
                $pengiriman->update([
                    'alamat_tujuan' => $mushafRequest->alamat_lengkap,
                    'nama_penerima' => $mushafRequest->nama_pengurus_1,
                    'nama_lembaga' => $mushafRequest->nama_lembaga,
                    'no_hp_penerima' => $mushafRequest->whatsapp_pengurus_1,
                    'catatan' => $catatan,
                    'jumlah_quran' => $totalApproved, // ✅ FIX: Use approved quantity
                ]);
            }

            // Mark request as processed
            $mushafRequest->markAsProcessed($pengiriman->id, auth()->id());

            \DB::commit();

            // ✅ FIX: Invalidate dashboard cache after processing to shipment
            $this->dashboardCache->invalidate(['dashboard', 'stats', 'activities']);

            return redirect()->route('admin.pengiriman.show', $pengiriman)
                ->with('success', "Permintaan berhasil diproses menjadi pengiriman {$pengiriman->no_resi}");

        } catch (\Exception $e) {
            \DB::rollback();

            return back()->withErrors([
                'error' => 'Gagal memproses ke pengiriman: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get donatur list and approved mushaf requests for processing
     */
    public function getDonaturList()
    {
        $this->authorize('viewAny', MushafRequest::class);

        $donaturList = Donatur::select('id', 'nama_donatur', 'kode_donatur')
            ->orderBy('nama_donatur')
            ->get();

        // Get approved mushaf requests that haven't been processed yet
        $approvedMushafRequests = MushafRequest::approved()
            ->whereNull('pengiriman_id') // Only those not yet processed
            ->select('id', 'no_request', 'nama_lembaga', 'alamat_lengkap', 'nama_pengurus_1', 'whatsapp_pengurus_1')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'donatur_list' => $donaturList,
            'approved_mushaf_requests' => $approvedMushafRequests,
        ]);
    }

    /**
     * Bulk update status
     */
    public function bulkUpdateStatus(Request $request)
    {
        $this->authorize('update', MushafRequest::class);

        $request->validate([
            'request_ids' => ['required', 'array'],
            'request_ids.*' => ['exists:mushaf_requests,id'],
            'status' => ['required', 'in:pending,reviewed,approved,rejected,processed,completed'],
            'catatan_admin' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->status === 'rejected' && empty($request->catatan_admin)) {
            return back()->withErrors(['error' => 'Catatan wajib diisi untuk penolakan']);
        }

        try {
            \DB::beginTransaction();

            $updated = 0;
            $mushafRequests = MushafRequest::whereIn('id', $request->request_ids)->get();

            foreach ($mushafRequests as $mushafRequest) {
                if ($request->status === 'approved') {
                    $mushafRequest->approve(auth()->id(), $request->catatan_admin);
                } else {
                    $mushafRequest->reject(auth()->id(), $request->catatan_admin);
                }
                $updated++;
            }

            \DB::commit();

            // ✅ FIX: Invalidate dashboard cache after bulk update
            $this->dashboardCache->invalidate(['dashboard', 'stats', 'activities']);

            $statusLabel = $request->status === 'approved' ? 'disetujui' : 'ditolak';

            return back()->with('success', "{$updated} permintaan berhasil {$statusLabel}");

        } catch (\Exception $e) {
            \DB::rollback();

            return back()->withErrors([
                'error' => 'Gagal bulk update: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Export mushaf requests to Excel
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', MushafRequest::class);

        try {
            // Get filters from request
            $filters = $request->only(['search', 'status', 'start_date', 'end_date']);

            // Generate filename with timestamp and filters
            $filename = 'mushaf-requests-'.now()->format('Y-m-d-H-i-s');

            if (isset($filters['status']) && $filters['status']) {
                $filename .= '-'.$filters['status'];
            }

            if (isset($filters['start_date']) && $filters['start_date']) {
                $filename .= '-from-'.$filters['start_date'];
            }

            if (isset($filters['end_date']) && $filters['end_date']) {
                $filename .= '-to-'.$filters['end_date'];
            }

            $filename .= '.xlsx';

            // Export using MushafRequestExport class
            return Excel::download(new MushafRequestExport($filters), $filename);

        } catch (\Exception $e) {
            \Log::error('Export mushaf requests error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'error' => 'Gagal melakukan export: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Import mushaf requests from Excel
     */
    public function import(Request $request)
    {
        $this->authorize('create', MushafRequest::class);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // Max 10MB
        ]);

        try {
            $import = new \App\Imports\MushafRequestImport;
            Excel::import($import, $request->file('file'));

            $results = $import->getResults();

            // Check if file was empty
            if ($results['success_count'] === 0 && $results['error_count'] === 0) {
                return back()->with('error', 'File Excel kosong atau tidak memiliki data. Pastikan file Excel berisi data sesuai template (minimal 1 baris data setelah header).');
            }

            if ($import->hasErrors()) {
                return back()->with([
                    'warning' => "Import selesai dengan {$results['success_count']} data berhasil dan {$results['error_count']} data gagal",
                    'import_errors' => $results['errors'],
                ]);
            }

            return back()->with('success', "Berhasil import {$results['success_count']} data mushaf request");

        } catch (\Exception $e) {
            return back()->with('error', 'Error saat import: '.$e->getMessage());
        }
    }

    /**
     * Download Excel import template
     */
    public function downloadTemplate()
    {
        $this->authorize('create', MushafRequest::class);

        try {
            $filename = 'mushaf-request-import-template-'.now()->format('Y-m-d').'.xlsx';

            return Excel::download(new \App\Exports\MushafRequestTemplateExport, $filename);

        } catch (\Exception $e) {
            return back()->with('error', 'Error saat download template: '.$e->getMessage());
        }
    }

    /**
     * Update approved quantities for mushaf request
     */
    public function updateQuantities(Request $request, MushafRequest $mushafRequest)
    {
        $this->authorize('update', $mushafRequest);

        $request->validate([
            'jumlah_mushaf_approved' => 'nullable|integer|min:0',
            'jumlah_mushaf_a5_approved' => 'nullable|integer|min:0',
            'jumlah_mushaf_a6_approved' => 'nullable|integer|min:0',
            'jumlah_iqra_approved' => 'nullable|integer|min:0',
            'catatan_perubahan_jumlah' => 'nullable|string|max:1000',
        ]);

        try {
            $originalTotal = $mushafRequest->total_mushaf;

            // Update quantities
            $mushafRequest->update($request->only([
                'jumlah_mushaf_approved',
                'jumlah_mushaf_a5_approved',
                'jumlah_mushaf_a6_approved',
                'jumlah_iqra_approved',
                'catatan_perubahan_jumlah',
            ]));

            $newTotal = $mushafRequest->fresh()->total_mushaf_approved;
            $difference = $newTotal - $originalTotal;
            $changeText = $difference > 0 ? "ditambah $difference" : 'dikurangi '.abs($difference);

            // ✅ FIX: Invalidate dashboard cache after quantity update
            $this->dashboardCache->invalidate(['dashboard', 'stats', 'activities']);

            return back()->with('success', "Jumlah mushaf yang disetujui berhasil diperbarui (dari {$originalTotal} menjadi {$newTotal}, {$changeText})");

        } catch (\Exception $e) {
            return back()->with('error', 'Error saat update jumlah: '.$e->getMessage());
        }
    }

    /**
     * Update mushaf request lembaga information
     */
    public function updateLembaga(Request $request, MushafRequest $mushafRequest)
    {
        $this->authorize('update', $mushafRequest);

        $request->validate([
            'nama_lembaga' => 'required|string|max:255',
            'kategori_lembaga' => 'required|string|max:255',
            'alamat_lengkap' => 'required|string',
            'provinsi' => 'nullable|string|max:255',
            'provinsi_id' => 'nullable|string|max:20',
            'kota_kabupaten' => 'nullable|string|max:255',
            'kota_kabupaten_id' => 'nullable|string|max:20',
            'kecamatan' => 'nullable|string|max:255',
            'kecamatan_id' => 'nullable|string|max:20',
            'kelurahan_desa' => 'nullable|string|max:255',
            'kelurahan_desa_id' => 'nullable|string|max:20',
            'kode_pos' => 'nullable|string|max:10',
            'alamat_detail' => 'nullable|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'urgensi_request' => 'required|string',
            'sumber_info' => 'nullable|string|max:255',
        ]);

        try {
            $data = $request->only([
                'nama_lembaga',
                'kategori_lembaga',
                'alamat_lengkap',
                'provinsi',
                'provinsi_id',
                'kota_kabupaten',
                'kota_kabupaten_id',
                'kecamatan',
                'kecamatan_id',
                'kelurahan_desa',
                'kelurahan_desa_id',
                'kode_pos',
                'alamat_detail',
                'latitude',
                'longitude',
                'urgensi_request',
                'sumber_info',
            ]);

            $mushafRequest->update($data);

            return back()->with('success', 'Informasi lembaga berhasil diperbarui');

        } catch (\Exception $e) {
            return back()->with('error', 'Error saat update informasi lembaga: '.$e->getMessage());
        }
    }

    /**
     * Update mushaf request kontak information
     */
    public function updateKontak(Request $request, MushafRequest $mushafRequest)
    {
        $this->authorize('update', $mushafRequest);

        $request->validate([
            'nama_pengurus_1' => 'required|string|max:255',
            'jabatan_pengurus_1' => 'required|string|max:255',
            'whatsapp_pengurus_1' => 'required|string|max:20',
            'nama_pengurus_2' => 'nullable|string|max:255',
            'jabatan_pengurus_2' => 'nullable|string|max:255',
            'whatsapp_pengurus_2' => 'nullable|string|max:20',
        ]);

        try {
            $data = $request->only([
                'nama_pengurus_1',
                'jabatan_pengurus_1',
                'whatsapp_pengurus_1',
                'nama_pengurus_2',
                'jabatan_pengurus_2',
                'whatsapp_pengurus_2',
            ]);

            $mushafRequest->update($data);

            return back()->with('success', 'Informasi kontak berhasil diperbarui');

        } catch (\Exception $e) {
            return back()->with('error', 'Error saat update informasi kontak: '.$e->getMessage());
        }
    }

    /**
     * Update mushaf request file uploads
     */
    public function updateFiles(Request $request, MushafRequest $mushafRequest)
    {
        $this->authorize('update', $mushafRequest);

        $request->validate([
            'foto_santri' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'foto_lembaga' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'file_nama_santri' => 'nullable|file|mimes:pdf,xlsx,xls,doc,docx|max:5120',
            'delete_foto_santri' => 'nullable',
            'delete_foto_lembaga' => 'nullable',
            'delete_file_nama_santri' => 'nullable',
        ]);

        try {
            $data = [];

            // Handle file deletions
            if ($request->delete_foto_santri && $mushafRequest->foto_santri_path) {
                \Storage::disk('public')->delete($mushafRequest->foto_santri_path);
                $data['foto_santri_path'] = null;
            }

            if ($request->delete_foto_lembaga && $mushafRequest->foto_lembaga_path) {
                \Storage::disk('public')->delete($mushafRequest->foto_lembaga_path);
                $data['foto_lembaga_path'] = null;
            }

            if ($request->delete_file_nama_santri && $mushafRequest->file_nama_santri_path) {
                \Storage::disk('public')->delete($mushafRequest->file_nama_santri_path);
                $data['file_nama_santri_path'] = null;
            }

            // Handle file uploads
            if ($request->hasFile('foto_santri')) {
                // Delete old file if exists
                if ($mushafRequest->foto_santri_path) {
                    \Storage::disk('public')->delete($mushafRequest->foto_santri_path);
                }
                $path = $request->file('foto_santri')->store('mushaf-requests/foto-santri', 'public');
                $data['foto_santri_path'] = $path;
            }

            if ($request->hasFile('foto_lembaga')) {
                // Delete old file if exists
                if ($mushafRequest->foto_lembaga_path) {
                    \Storage::disk('public')->delete($mushafRequest->foto_lembaga_path);
                }
                $path = $request->file('foto_lembaga')->store('mushaf-requests/foto-lembaga', 'public');
                $data['foto_lembaga_path'] = $path;
            }

            if ($request->hasFile('file_nama_santri')) {
                // Delete old file if exists
                if ($mushafRequest->file_nama_santri_path) {
                    \Storage::disk('public')->delete($mushafRequest->file_nama_santri_path);
                }
                $path = $request->file('file_nama_santri')->store('mushaf-requests/file-nama-santri', 'public');
                $data['file_nama_santri_path'] = $path;
            }

            if (! empty($data)) {
                $mushafRequest->update($data);
            }

            return back()->with('success', 'File lampiran berhasil diperbarui');

        } catch (\Exception $e) {
            return back()->with('error', 'Error saat update file: '.$e->getMessage());
        }
    }

    /**
     * Delete mushaf request (allowed for all status except completed)
     */
    public function destroy(MushafRequest $mushafRequest)
    {
        // Prevent deletion of completed requests
        if ($mushafRequest->status === 'completed') {
            return back()->with('error', 'Tidak dapat menghapus request yang sudah selesai (completed). Data ini adalah historical record.');
        }

        try {
            // Delete associated files if exists
            if ($mushafRequest->foto_santri_path) {
                \Storage::disk('public')->delete($mushafRequest->foto_santri_path);
            }
            if ($mushafRequest->foto_lembaga_path) {
                \Storage::disk('public')->delete($mushafRequest->foto_lembaga_path);
            }
            if ($mushafRequest->file_nama_santri_path) {
                \Storage::disk('public')->delete($mushafRequest->file_nama_santri_path);
            }

            $no_request = $mushafRequest->no_request;
            $mushafRequest->delete();

            // ✅ FIX: Invalidate dashboard cache after deletion
            $this->dashboardCache->invalidate(['dashboard', 'stats', 'activities']);

            return back()->with('success', "Request {$no_request} berhasil dihapus");

        } catch (\Exception $e) {
            return back()->with('error', 'Error saat menghapus request: '.$e->getMessage());
        }
    }
}
