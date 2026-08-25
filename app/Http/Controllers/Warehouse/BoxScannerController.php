<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\MushafRequest;
use App\Models\BulkOperationLog;
use App\Models\StatusHistory;
use App\Models\JobProgress;
use App\Jobs\Warehouse\BulkStatusUpdateJob;
use App\Jobs\Warehouse\BulkBoxProcessingJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Inertia\Inertia;
use App\Services\Cache\StatusPengirimanCache;

class BoxScannerController extends Controller
{
    public function __construct()
    {
    }

    /**
     * Display box scanner page
     */
    public function index()
    {
        // Additional authorization check
        Gate::authorize('viewScanner', auth()->user());
        
        $statusList = StatusPengiriman::orderBy('urutan')->get();
        $stats = $this->getBoxScannerStats();
        $activeJobs = JobProgress::getActiveJobsForUser(auth()->id());
        $recentJobs = JobProgress::getRecentJobsForUser(auth()->id(), 5);
        
        return Inertia::render('Warehouse/BoxScanner', [
            'statusList' => $statusList,
            'stats' => $stats,
            'activeJobs' => $activeJobs,
            'recentJobs' => $recentJobs,
        ]);
    }
    
    /**
     * Scan box QR code and get details
     */
    public function scanBox(Request $request)
    {
        // Enhanced input validation
        $request->validate([
            'qr_code' => [
                'required',
                'string',
                'max:500', // Prevent overly long inputs
                'regex:/^[A-Za-z0-9\-\{\}\":\s,_]+$/' // Allow only safe characters
            ]
        ]);
        
        // Additional authorization check
        Gate::authorize('viewScanner', auth()->user());
        
        try {
            // Sanitize input
            $qrCode = trim(strip_tags($request->qr_code));
            $boxCode = null;
            
            // Try to decode JSON first (old format)
            $decoded = json_decode($qrCode, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['kode_kerdus'])) {
                $boxCode = $decoded['kode_kerdus'];
            } else {
                // New simple format - just box code
                $boxCode = $qrCode;
            }
            
            // Validate box code format
            if (!preg_match('/^KB-\d{8}-\d{3}-[A-Z0-9]+-\d{2}$/', $boxCode)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format kode box tidak valid'
                ], 400);
            }
            
            // Find box
            $box = PackingBox::with([
                'dailyPackingTask.user',
                'jenisQuran',
                'packingItems.pengiriman.donatur',
                'packingItems.pengiriman.mushafRequest',
                'packingItems.pengiriman.status'
            ])
            ->where('kode_kerdus', $boxCode)
            ->first();
            
            if (!$box) {
                // Log failed box scan attempts for security monitoring
                Log::warning('Box scan attempt with invalid code', [
                    'user_id' => auth()->id(),
                    'scanned_code' => $boxCode,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Box tidak ditemukan'
                ], 404);
            }
            
            // Check if user has authorization to access this specific box
            Gate::authorize('accessBox', $box);
            
            // Log successful box access for audit trail
            Log::info('Box accessed via scanner', [
                'user_id' => auth()->id(),
                'box_id' => $box->id,
                'box_code' => $box->kode_kerdus,
                'user_role' => auth()->user()->role
            ]);
            
            // Get box summary
            $summary = $this->getBoxSummary($box);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'box' => [
                        'id' => $box->id,
                        'kode_kerdus' => $box->kode_kerdus,
                        'status' => $box->status,
                        'status_display' => $this->mapBoxStatus($box->status),
                        'seal_code' => $box->seal_code,
                        'sealed_at' => $box->sealed_at?->format('d/m/Y H:i'),
                        'jenis_quran' => $box->jenisQuran?->nama_jenis ?? 'Unknown',
                        'kapasitas' => $box->kapasitas,
                        'terisi' => $box->jumlah_terisi,
                        'user_name' => $box->dailyPackingTask->user->name ?? 'Unknown'
                    ],
                    'summary' => $summary,
                    'items' => $this->getBoxItems($box)
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Box scanner error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update status for all items in box (single box - immediate processing)
     */
    public function updateStatus(Request $request)
    {
        // Enhanced input validation with security measures
        $request->validate([
            'box_id' => 'required|integer|exists:packing_boxes,id',
            'new_status_id' => 'required|integer|exists:status_pengiriman,id',
            'address' => [
                'nullable',
                'string',
                'max:1000',
                'regex:/^[a-zA-Z0-9\s\.,\-\/\(\)]+$/' // Only allow safe address characters
            ],
            'notes' => [
                'nullable', 
                'string', 
                'max:500',
                'regex:/^[a-zA-Z0-9\s\.,\-\!\?]+$/' // Only allow safe note characters
            ],
            'location' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\.,\-]+$/' // Only allow safe location characters
            ],
            'documentation' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'mimetypes:image/jpeg,image/png,application/pdf',
                'max:5120', // Max 5MB
                'extensions:jpg,jpeg,png,pdf'
            ]
        ]);
        
        // Check file upload authorization
        if ($request->hasFile('documentation')) {
            Gate::authorize('uploadDocumentation', auth()->user());
        }
        
        DB::beginTransaction();
        try {
            $box = PackingBox::with('packingItems.pengiriman.status')->find($request->box_id);

            // Respect granular permission for sealed boxes: only block if sealed AND user lacks override
            $isSealed = (bool) ($box->is_sealed ?? false);
            if (!$isSealed && isset($box->status)) {
                $isSealed = $box->status === 'sealed';
            }
            if ($isSealed) {
                $user = auth()->user();
                $hasOverride = $user->can('warehouse.box.update_sealed')
                    || $user->can('warehouse.box.update_any')
                    || $user->can('supervisor.warehouse.monitor');
                if (! $hasOverride) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Box sudah tersegel. Diperlukan izin untuk mengubah status box tersegel.',
                    ], 403);
                }
            }

            // Authorization check for this specific box with explicit handling
            try {
                Gate::authorize('updateBoxStatus', $box);
            } catch (AuthorizationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak berhak mengupdate status untuk box ini. Pastikan box termasuk dalam tugas harian Anda atau gunakan akun Supervisor.',
                ], 403);
            }
            
            // Log status update attempt for audit trail
            Log::info('Box status update initiated', [
                'user_id' => auth()->id(),
                'box_id' => $box->id,
                'box_code' => $box->kode_kerdus,
                'new_status_id' => $request->new_status_id,
                'has_documentation' => $request->hasFile('documentation'),
                'ip_address' => $request->ip()
            ]);
            $newStatus = StatusPengiriman::find($request->new_status_id);
            
            // Get all pengiriman with their current status
            $pengirimanItems = $box->packingItems;
            $pengirimanIds = $pengirimanItems->pluck('pengiriman_id')->toArray();
            
            // Store old statuses for history
            $oldStatuses = [];
            foreach ($pengirimanItems as $item) {
                $oldStatuses[$item->pengiriman_id] = $item->pengiriman->status_id;
            }
            
            // Prepare update data
            $updateData = [
                'status_id' => $request->new_status_id
            ];
            
            // Add optional address if provided
            if ($request->filled('address')) {
                $updateData['alamat_tujuan'] = $request->address;
            }
            
            // Handle file upload if provided with enhanced security
            $documentationPath = null;
            if ($request->hasFile('documentation')) {
                $file = $request->file('documentation');
                
                // Additional security checks
                if (!$file->isValid()) {
                    throw new \Exception('File upload gagal atau file rusak');
                }
                
                // Validate file size (double check)
                if ($file->getSize() > 5242880) { // 5MB in bytes
                    throw new \Exception('File terlalu besar. Maksimal 5MB');
                }
                
                // Validate MIME type (double check)
                $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
                if (!in_array($file->getMimeType(), $allowedMimes)) {
                    throw new \Exception('Tipe file tidak diizinkan. Hanya JPG, PNG, dan PDF');
                }
                
                // Generate secure filename
                $extension = $file->getClientOriginalExtension();
                $filename = 'status_update_' . preg_replace('/[^A-Za-z0-9\-]/', '', $box->kode_kerdus) . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                
                // Store with additional path validation
                $documentationPath = $file->storeAs('status_updates', $filename, 'public');
                
                // Verify the file was actually stored
                if (!$documentationPath || !file_exists(storage_path('app/public/' . $documentationPath))) {
                    throw new \Exception('Gagal menyimpan file dokumentasi');
                }
                
                // Log file upload for security audit
                Log::info('Documentation file uploaded', [
                    'user_id' => auth()->id(),
                    'box_code' => $box->kode_kerdus,
                    'filename' => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType()
                ]);
            }
            
            // Update all pengiriman status
            $updated = Pengiriman::whereIn('id', $pengirimanIds)->update($updateData);
            
            // Create status history for each pengiriman
            foreach ($pengirimanIds as $pengirimanId) {
                $oldStatusId = $oldStatuses[$pengirimanId] ?? null;
                
                // Only create history if status actually changed
                if ($oldStatusId != $request->new_status_id) {
                    $historyData = [
                        'pengiriman_id' => $pengirimanId,
                        'status_from' => $oldStatusId,
                        'status_to' => $request->new_status_id,
                        'catatan' => 'Update dari Box Scanner: ' . $box->kode_kerdus,
                        'created_by' => auth()->id()
                    ];
                    
                    // Add location if provided
                    if ($request->filled('location')) {
                        $historyData['lokasi'] = $request->location;
                    }
                    
                    // Add documentation if provided
                    if ($documentationPath) {
                        $historyData['dokumentasi'] = [
                            [
                                'path' => str_replace('status_updates/', '', $documentationPath),
                                'original_name' => $request->file('documentation')->getClientOriginalName(),
                                'url' => asset('storage/' . $documentationPath)
                            ]
                        ];
                    }
                    
                    StatusHistory::create($historyData);
                }
            }
            
            // Log bulk operation
            BulkOperationLog::logOperation([
                'operation_type' => 'status_update',
                'box_code' => $box->kode_kerdus,
                'box_seal_code' => $box->seal_code,
                'pengiriman_ids' => $pengirimanIds,
                'new_status_id' => $request->new_status_id,
                'new_address' => $request->filled('address') ? $request->address : null,
                'notes' => $request->filled('notes') ? $request->notes : null
            ]);
            
            DB::commit();
            
            $message = "Berhasil update status {$updated} pengiriman ke {$newStatus->nama}";
            if ($request->filled('address')) {
                $message .= " dan alamat";
            }
            if ($documentationPath) {
                $message .= " dengan dokumentasi";
            }
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'updated_count' => $updated,
                    'new_status' => $newStatus->nama,
                    'address_updated' => $request->filled('address'),
                    'documentation_uploaded' => $documentationPath ? true : false
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Update status error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal update status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update status for multiple boxes (using queue)
     */
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'box_ids' => 'required|array|min:1',
            'box_ids.*' => 'integer|exists:packing_boxes,id',
            'new_status_id' => 'required|integer|exists:status_pengiriman,id',
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:500',
            'location' => 'nullable|string|max:255',
            'documentation' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120'
            ]
        ]);

        try {
            // Handle file upload if provided
            $documentationPath = null;
            if ($request->hasFile('documentation')) {
                $file = $request->file('documentation');
                $filename = 'bulk_update_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file->getClientOriginalExtension();
                $documentationPath = $file->storeAs('status_updates', $filename, 'public');
            }

            // Dispatch job
            $job = BulkStatusUpdateJob::dispatch(
                $request->box_ids,
                $request->new_status_id,
                $request->address,
                $request->notes,
                $request->location,
                $documentationPath,
                auth()->id()
            );

            $newStatus = StatusPengiriman::find($request->new_status_id);

            return response()->json([
                'success' => true,
                'message' => 'Bulk update status telah dijadwalkan dan akan diproses di background',
                'data' => [
                    'job_id' => $job->getJobId(),
                    'box_count' => count($request->box_ids),
                    'new_status' => $newStatus->nama,
                    'estimated_items' => $this->estimateItemCount($request->box_ids)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk update status error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menjadwalkan bulk update: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk box operations (seal, unseal, validate, archive)
     */
    public function bulkBoxOperation(Request $request)
    {
        $request->validate([
            'box_codes' => 'required|array|min:1',
            'box_codes.*' => 'string',
            'operation' => 'required|string|in:seal,unseal,validate,archive',
            'parameters' => 'nullable|array'
        ]);

        try {
            // Dispatch job
            $job = BulkBoxProcessingJob::dispatch(
                $request->box_codes,
                $request->operation,
                $request->parameters ?? [],
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => "Bulk {$request->operation} operation telah dijadwalkan",
                'data' => [
                    'job_id' => $job->getJobId(),
                    'box_count' => count($request->box_codes),
                    'operation' => $request->operation
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk box operation error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menjadwalkan bulk operation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get job progress
     */
    public function getJobProgress(Request $request)
    {
        $request->validate([
            'job_id' => 'required|string'
        ]);

        $jobProgress = JobProgress::where('job_id', $request->job_id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$jobProgress) {
            return response()->json([
                'success' => false,
                'message' => 'Job tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => $jobProgress->job_id,
                'job_type' => $jobProgress->job_type,
                'title' => $jobProgress->title,
                'status' => $jobProgress->status,
                'status_display' => $jobProgress->status_display,
                'progress_percentage' => $jobProgress->progress_percentage,
                'total_items' => $jobProgress->total_items,
                'processed_items' => $jobProgress->processed_items,
                'failed_items' => $jobProgress->failed_items,
                'success_rate' => $jobProgress->success_rate,
                'duration_formatted' => $jobProgress->duration_formatted,
                'metadata' => $jobProgress->metadata,
                'results' => $jobProgress->results,
                'error_message' => $jobProgress->error_message,
                'created_at' => $jobProgress->created_at?->format('d/m/Y H:i:s'),
                'started_at' => $jobProgress->started_at?->format('d/m/Y H:i:s'),
                'completed_at' => $jobProgress->completed_at?->format('d/m/Y H:i:s')
            ]
        ]);
    }

    /**
     * Get user's job history
     */
    public function getJobHistory(Request $request)
    {
        $limit = $request->get('limit', 20);
        $status = $request->get('status');
        $type = $request->get('type');

        $query = JobProgress::byUser(auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($status) {
            $query->where('status', $status);
        }

        if ($type) {
            $query->byType($type);
        }

        $jobs = $query->get();

        return response()->json([
            'success' => true,
            'data' => $jobs->map(function ($job) {
                return [
                    'job_id' => $job->job_id,
                    'job_type' => $job->job_type,
                    'title' => $job->title,
                    'status' => $job->status,
                    'status_display' => $job->status_display,
                    'progress_percentage' => $job->progress_percentage,
                    'total_items' => $job->total_items,
                    'processed_items' => $job->processed_items,
                    'failed_items' => $job->failed_items,
                    'success_rate' => $job->success_rate,
                    'duration_formatted' => $job->duration_formatted,
                    'created_at' => $job->created_at?->format('d/m/Y H:i:s'),
                    'completed_at' => $job->completed_at?->format('d/m/Y H:i:s')
                ];
            })
        ]);
    }
    
    /**
     * Set address from approved Mushaf Request
     */
    public function setMushafRequestAddress(Request $request)
    {
        // Enhanced input validation
        $request->validate([
            'box_id' => 'required|integer|exists:packing_boxes,id',
            'mushaf_request_id' => 'required|integer|exists:mushaf_requests,id'
        ]);
        
        DB::beginTransaction();
        try {
            $box = PackingBox::with('packingItems')->find($request->box_id);
            
            // Authorization check for this specific box
            Gate::authorize('updateBoxStatus', $box);
            
            $mushafRequest = MushafRequest::find($request->mushaf_request_id);
            
            // Log mushaf address update attempt
            Log::info('Mushaf request address update initiated', [
                'user_id' => auth()->id(),
                'box_id' => $box->id,
                'box_code' => $box->kode_kerdus,
                'mushaf_request_id' => $mushafRequest->id,
                'ip_address' => $request->ip()
            ]);
            
            // Validate mushaf request is approved
            if ($mushafRequest->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Mushaf Request belum disetujui'
                ], 400);
            }
            
            // Get all pengiriman IDs
            $pengirimanIds = $box->packingItems->pluck('pengiriman_id')->toArray();
            
            // Update all pengiriman with mushaf request address
            $updated = Pengiriman::whereIn('id', $pengirimanIds)
                ->update([
                    'nama_penerima' => $mushafRequest->nama_lembaga,
                    'alamat_tujuan' => $mushafRequest->alamat_lengkap,
                    'no_hp_penerima' => $mushafRequest->whatsapp_pengurus_1,
                    'nama_lembaga' => $mushafRequest->nama_lembaga,
                    'mushaf_request_id' => $mushafRequest->id
                ]);
            
            // Log bulk operation
            BulkOperationLog::logOperation([
                'operation_type' => 'address_update',
                'box_code' => $box->kode_kerdus,
                'box_seal_code' => $box->seal_code,
                'pengiriman_ids' => $pengirimanIds,
                'new_address' => $mushafRequest->alamat_lengkap,
                'notes' => "Set alamat dari Mushaf Request: {$mushafRequest->nama_lembaga}"
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Berhasil set alamat {$updated} pengiriman ke {$mushafRequest->nama_lembaga}",
                'data' => [
                    'updated_count' => $updated,
                    'nama_lembaga' => $mushafRequest->nama_lembaga,
                    'alamat' => $mushafRequest->alamat_lengkap
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Set mushaf address error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal set alamat: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get box summary data
     */
    private function getBoxSummary($box)
    {
        $items = $box->packingItems;
        
        // Group by status
        $statusGroups = $items->groupBy(function($item) {
            return $item->pengiriman->status->nama ?? 'Unknown';
        })->map->count();
        
        // Group by destination
        $destinations = $items->groupBy(function($item) {
            return $item->pengiriman->kota_kabupaten ?? 'Unknown';
        })->map->count();
        
        // Get all approved mushaf requests (not just from current box items)
        $mushafRequests = \App\Models\MushafRequest::where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return [
            'total_items' => $items->count(),
            'status_breakdown' => $statusGroups,
            'destinations' => $destinations,
            'has_mushaf_request' => $mushafRequests->count() > 0,
            'mushaf_requests' => $mushafRequests->map(function($mr) {
                return [
                    'id' => $mr->id,
                    'no_request' => $mr->no_request,
                    'nama_lembaga' => $mr->nama_lembaga,
                    'status' => $mr->status,
                    'alamat' => $mr->alamat_lengkap,
                    'whatsapp_pengurus_1' => $mr->whatsapp_pengurus_1,
                    'provinsi' => $mr->provinsi,
                    'kota_kabupaten' => $mr->kota_kabupaten,
                    'kecamatan' => $mr->kecamatan,
                    'kelurahan_desa' => $mr->kelurahan_desa,
                    'kode_pos' => $mr->kode_pos,
                    'created_at' => $mr->created_at->format('d/m/Y')
                ];
            })->values()
        ];
    }
    
    /**
     * Get box items details
     */
    private function getBoxItems($box)
    {
        return $box->packingItems->map(function($item) {
            $pengiriman = $item->pengiriman;
            
            return [
                'id' => $pengiriman->id,
                'no_resi' => $pengiriman->no_resi,
                'nama_penerima' => $pengiriman->nama_penerima,
                'alamat_tujuan' => $pengiriman->alamat_tujuan,
                'status' => $pengiriman->status->nama ?? 'Unknown',
                'status_id' => $pengiriman->status_id,
                'mushaf_request_id' => $pengiriman->mushaf_request_id,
                'donatur' => $pengiriman->donatur->nama_donatur ?? 'Unknown'
            ];
        });
    }
    
    /**
     * Get scanner stats
     */
    private function getBoxScannerStats()
    {
        $today = now()->startOfDay();
        
        return [
            'boxes_scanned_today' => BulkOperationLog::where('operation_type', 'status_update')
                ->where('created_at', '>=', $today)
                ->distinct('box_code')
                ->count('box_code'),
            'items_updated_today' => BulkOperationLog::where('created_at', '>=', $today)
                ->sum('items_count'),
            'active_boxes' => PackingBox::where('status', 'sealed')->count(),
            'pending_shipment' => Pengiriman::where('status_id', 
                StatusPengirimanCache::getIdBySlug('siap-kirim')
            )->count(),
            'active_jobs' => JobProgress::active()->byUser(auth()->id())->count(),
            'completed_jobs_today' => JobProgress::completed()
                ->byUser(auth()->id())
                ->whereDate('completed_at', today())
                ->count()
        ];
    }

    /**
     * Estimate total item count for given box IDs
     */
    private function estimateItemCount(array $boxIds)
    {
        return PackingBox::whereIn('id', $boxIds)
            ->withCount('packingItems')
            ->get()
            ->sum('packing_items_count');
    }

    /**
     * Map internal box status to a user-friendly Indonesian label
     */
    private function mapBoxStatus(?string $status): string
    {
        if ($status === null || $status === '') {
            return 'Tidak ada';
        }

        $map = [
            'filling' => 'Pengisian',
            'ready_for_seal' => 'Siap Segel',
            'sealed' => 'Tersegel',
            'archived' => 'Diarsipkan',
            'in_transit' => 'Dalam Pengiriman',
            'delivered' => 'Terkirim',
        ];

        if (array_key_exists($status, $map)) {
            return $map[$status];
        }

        // Sensible fallback: prettify slug/case
        $pretty = str_replace(['-', '_'], ' ', strtolower($status));
        return ucwords($pretty);
    }
}
