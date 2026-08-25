<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Jobs\Certificate\GenerateBulkCertificatesJob;
use App\Jobs\Certificate\GenerateSingleCertificateJob;
use App\Jobs\Warehouse\CertificateGenerationJob;
use App\Models\Donatur;
use App\Models\JobProgress;
use App\Models\Sertifikat;
use App\Models\WakafBatch;
use App\Services\BatchCertificateService;
use App\Services\ConsolidatedCertificateService;
use App\Services\OnDemandCertificateService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CertificateController extends Controller
{
    protected $onDemandService;

    protected $consolidatedService;

    protected $batchCertificateService;

    public function __construct(
        OnDemandCertificateService $onDemandService,
        ConsolidatedCertificateService $consolidatedService,
        BatchCertificateService $batchCertificateService
    ) {
        $this->onDemandService = $onDemandService;
        $this->consolidatedService = $consolidatedService;
        $this->batchCertificateService = $batchCertificateService;
    }

    /**
     * Display certificates management page
     */
    public function index(Request $request)
    {
        auth()->user()->can(PermissionEnum::CERTIFICATES_READ->value);

        $query = Sertifikat::with(['wakafBatch.donatur', 'wakafBatch.jenisQuran', 'wakafBatch.pengiriman', 'donatur', 'generator'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_sertifikat', 'like', "%{$search}%")
                    ->orWhereHas('wakafBatch', function ($batchQ) use ($search) {
                        $batchQ->where('batch_code', 'like', "%{$search}%")
                            ->orWhereHas('donatur', function ($donaturQ) use ($search) {
                                $donaturQ->where('nama_donatur', 'like', "%{$search}%");
                            });
                    })
                    ->orWhereHas('donatur', function ($donaturQ) use ($search) {
                        $donaturQ->where('nama_donatur', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('sent_status')) {
            $query->where('is_sent', $request->sent_status === 'sent');
        }

        // Date range filters
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Paginate results
        $certificates = $query->paginate(20)->appends($request->query());

        // Add wakif names to each certificate
        $certificates->getCollection()->transform(function ($certificate) {
            if ($certificate->wakafBatch && $certificate->wakafBatch->donatur) {
                // Get wakif names from all pengiriman of this donatur
                $wakifNames = \App\Models\Pengiriman::where('donatur_id', $certificate->wakafBatch->donatur_id)
                    ->with('wakafItem:id,pengiriman_id,wakif_name')
                    ->get()
                    ->map(function ($p) {
                        return $p->wakafItem ? $p->wakafItem->wakif_name : null;
                    })
                    ->filter()
                    ->unique()
                    ->values();

                $certificate->wakif_names = $wakifNames->toArray();
                $certificate->wakif_names_string = $wakifNames->implode(', ');
            } else {
                $certificate->wakif_names = [];
                $certificate->wakif_names_string = '';
            }

            return $certificate;
        });

        // Get statistics
        $stats = [
            'total' => Sertifikat::count(),
            'today' => Sertifikat::generatedToday()->count(),
            'month' => Sertifikat::generatedThisMonth()->count(),
            'not_sent' => Sertifikat::notSent()->count(),
        ];

        return Inertia::render('Admin/Certificates/Index', [
            'certificates' => $certificates,
            'stats' => $stats,
            'filters' => $request->only(['search', 'sent_status', 'start_date', 'end_date']),
        ]);
    }

    /**
     * Generate certificate on-demand (small jobs) or dispatch to queue (larger jobs)
     */
    public function generateForBatch(Request $request, WakafBatch $wakafBatch)
    {
        auth()->user()->can(PermissionEnum::CERTIFICATES_GENERATE->value);

        $request->validate([
            'template_id' => 'nullable|exists:certificate_templates,id',
            'async' => 'boolean', // Force async processing
        ]);

        try {
            $options = [
                'template_id' => $request->input('template_id'),
                'regenerate' => $request->boolean('regenerate', false),
            ];

            // For single certificates, check if should process async
            $forceAsync = $request->boolean('async', false);
            $shouldProcessAsync = $forceAsync || $this->shouldProcessAsync($wakafBatch);

            if ($shouldProcessAsync) {
                // Dispatch to background job
                $job = GenerateSingleCertificateJob::dispatch(
                    $wakafBatch->id,
                    $options,
                    auth()->id()
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Sertifikat sedang diproses di background',
                    'job_dispatched' => true,
                    'data' => [
                        'wakaf_batch_id' => $wakafBatch->id,
                        'batch_code' => $wakafBatch->batch_code,
                        'job_status_url' => route('api.jobs.active'),
                        'processing_message' => 'Anda akan menerima notifikasi setelah sertifikat selesai dibuat.',
                    ],
                ]);
            } else {
                // Generate immediately for quick processing
                return $this->onDemandService->generateOnDemand($wakafBatch, $options);
            }

        } catch (Exception $e) {
            Log::error('Failed to generate certificate', [
                'wakaf_batch_id' => $wakafBatch->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat sertifikat: '.$e->getMessage(),
            ], 422);
        }
    }

    // Legacy method - redirected to new bulk generation
    public function bulkGenerate(Request $request)
    {
        auth()->user()->can(PermissionEnum::CERTIFICATES_GENERATE->value);

        // Redirect to new bulk certificate generation method
        return $this->generateBulkCertificates($request);
    }

    /**
     * Download certificate PDF by Sertifikat ID - IMPROVED VERSION
     */
    public function download($id)
    {
        auth()->user()->can(PermissionEnum::CERTIFICATES_DOWNLOAD->value);

        try {
            $sertifikat = Sertifikat::with(['wakafBatch', 'donatur'])->findOrFail($id);

            Log::info('Downloading certificate on-demand', [
                'certificate_id' => $id,
                'is_consolidated' => $sertifikat->is_consolidated,
                'user_id' => auth()->id(),
            ]);

            if ($sertifikat->is_consolidated) {
                // Handle consolidated certificate
                if (! $sertifikat->donatur) {
                    abort(404, 'Data donatur tidak ditemukan');
                }

                $pdf = $this->consolidatedService->generateConsolidatedCertificate($sertifikat->donatur);
                $donaturName = $this->consolidatedService->sanitizeFilename($sertifikat->donatur->nama_donatur);
                $generateDate = now()->format('Y-m-d');
                $filename = "Ekspedisi-Quran-{$generateDate}-{$sertifikat->nomor_sertifikat}-{$donaturName}.pdf";
            } else {
                // Handle batch certificate (new method with multiple wakif)
                if (! $sertifikat->wakafBatch) {
                    abort(404, 'Data batch wakaf tidak ditemukan');
                }

                // Get wakif items for this batch
                $wakifItems = $this->batchCertificateService->getWakifItemsForBatch($sertifikat->wakafBatch);
                $groupedWakifs = $wakifItems->groupBy('wakif_name');

                // Generate PDF using batch certificate service
                $pdfContent = $this->batchCertificateService->generateConsolidatedPdf(
                    $sertifikat->wakafBatch,
                    $groupedWakifs,
                    $this->batchCertificateService->getTemplate()
                );

                $donaturName = $sertifikat->wakafBatch->donatur->nama_donatur;
                $filename = "Batch-Sertifikat-{$sertifikat->nomor_sertifikat}-{$donaturName}.pdf";

                return response($pdfContent, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                ]);
            }

            return $pdf->download($filename);

        } catch (Exception $e) {
            Log::error('Certificate download failed', [
                'certificate_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return JSON response untuk AJAX requests
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mendownload sertifikat: '.$e->getMessage(),
                ], 404);
            }

            abort(404, 'Gagal membuat sertifikat: '.$e->getMessage());
        }
    }

    /**
     * Generate public download token for certificate
     */
    public function generateToken($id)
    {
        auth()->user()->can(PermissionEnum::CERTIFICATES_READ->value);

        try {
            $sertifikat = Sertifikat::findOrFail($id);
            $token = $this->onDemandService->generateDownloadToken($sertifikat);
            $publicUrl = $this->onDemandService->getPublicDownloadUrl($sertifikat);

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'public_url' => $publicUrl,
                    'expires_in' => '24 hours',
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat link download: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Preview certificate in browser
     */
    public function preview($id)
    {
        auth()->user()->can(PermissionEnum::CERTIFICATES_READ->value);

        try {
            Log::info("Preview request for certificate ID: $id");

            $sertifikat = Sertifikat::with(['wakafBatch', 'donatur'])->findOrFail($id);
            Log::info("Certificate found: {$sertifikat->nomor_sertifikat}", [
                'is_consolidated' => $sertifikat->is_consolidated,
            ]);

            if ($sertifikat->is_consolidated) {
                // Handle consolidated certificate preview
                if (! $sertifikat->donatur) {
                    Log::error("Donatur not found for consolidated certificate ID: $id");
                    abort(404, 'Data donatur tidak ditemukan');
                }

                Log::info("Donatur found: {$sertifikat->donatur->nama_donatur}");
                $pdf = $this->consolidatedService->generateConsolidatedCertificate($sertifikat->donatur);
                $donaturName = $this->consolidatedService->sanitizeFilename($sertifikat->donatur->nama_donatur);
                $generateDate = now()->format('Y-m-d');
                $filename = "Preview-Ekspedisi-Quran-{$generateDate}-{$sertifikat->nomor_sertifikat}-{$donaturName}.pdf";
            } else {
                // Handle batch certificate preview (new method with multiple wakif)
                if (! $sertifikat->wakafBatch) {
                    Log::error("WakafBatch not found for certificate ID: $id");
                    abort(404, 'Data batch wakaf tidak ditemukan');
                }

                Log::info("WakafBatch found: {$sertifikat->wakafBatch->batch_code}");

                // Get wakif items for this batch
                $wakifItems = $this->batchCertificateService->getWakifItemsForBatch($sertifikat->wakafBatch);
                $groupedWakifs = $wakifItems->groupBy('wakif_name');

                // Generate PDF using batch certificate service
                $pdfContent = $this->batchCertificateService->generateConsolidatedPdf(
                    $sertifikat->wakafBatch,
                    $groupedWakifs,
                    $this->batchCertificateService->getTemplate()
                );

                $donaturName = $sertifikat->wakafBatch->donatur->nama_donatur;
                $filename = "Preview-Batch-Sertifikat-{$sertifikat->nomor_sertifikat}-{$donaturName}.pdf";

                Log::info("PDF generated successfully for certificate ID: $id");

                return response($pdfContent, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "inline; filename=\"{$filename}\"",
                ]);
            }

            Log::info("PDF generated successfully for certificate ID: $id");

            return $pdf->stream($filename);

        } catch (Exception $e) {
            Log::error("Preview failed for certificate ID: $id", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return JSON error instead of abort() for better debugging
            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * View certificate inline by Sertifikat ID (now using on-demand generation)
     */
    public function view(Sertifikat $sertifikat)
    {
        auth()->user()->can(PermissionEnum::CERTIFICATES_READ->value);

        // Redirect to preview method which handles on-demand generation
        return $this->preview($sertifikat);
    }

    /**
     * Generate fresh certificate for a WakafBatch (replaces regenerate concept)
     */
    public function regenerateForBatch(Request $request, WakafBatch $wakafBatch)
    {
        auth()->user()->can(PermissionEnum::CERTIFICATES_GENERATE->value);

        // Since we're using on-demand generation, "regenerate" just means
        // generate with latest data - same as generateForBatch
        return $this->generateForBatch($request, $wakafBatch);
    }

    /**
     * Create consolidated certificate records for donors (one certificate per donor)
     */

    /**
     * Create missing WakafBatches for donatur that don't have them yet
     */
    private function createMissingWakafBatches()
    {
        // Find donatur that don't have WakafBatches but have donations
        $donatursWithoutBatches = \App\Models\Donatur::whereDoesntHave('wakafBatches')
            ->where(function ($query) {
                $query->where('total_a5_count', '>', 0)
                    ->orWhere('total_a6_count', '>', 0)
                    ->orWhere('total_iqra_count', '>', 0);
            })
            ->get();

        foreach ($donatursWithoutBatches as $donatur) {
            // Create WakafBatch for each jenis_quran that has count > 0
            if ($donatur->total_a5_count > 0) {
                $jenisQuran = \App\Models\JenisQuran::where('kode_jenis', 'A5')->first();
                if ($jenisQuran) {
                    \App\Models\WakafBatch::create([
                        'batch_code' => 'WB-'.date('Y').'-'.str_pad(\App\Models\WakafBatch::count() + 1, 5, '0', STR_PAD_LEFT),
                        'donatur_id' => $donatur->id,
                        'jenis_quran_id' => $jenisQuran->id,
                        'total_quran' => $donatur->total_a5_count,
                        'tanggal_wakaf' => $donatur->donation_date ?? now(),
                        'status' => 'pending_distribution',
                        'created_by' => auth()->id() ?? 1,
                    ]);
                }
            }

            if ($donatur->total_a6_count > 0) {
                $jenisQuran = \App\Models\JenisQuran::where('kode_jenis', 'A6')->first();
                if ($jenisQuran) {
                    \App\Models\WakafBatch::create([
                        'batch_code' => 'WB-'.date('Y').'-'.str_pad(\App\Models\WakafBatch::count() + 1, 5, '0', STR_PAD_LEFT),
                        'donatur_id' => $donatur->id,
                        'jenis_quran_id' => $jenisQuran->id,
                        'total_quran' => $donatur->total_a6_count,
                        'tanggal_wakaf' => $donatur->donation_date ?? now(),
                        'status' => 'pending_distribution',
                        'created_by' => auth()->id() ?? 1,
                    ]);
                }
            }

            if ($donatur->total_iqra_count > 0) {
                $jenisQuran = \App\Models\JenisQuran::where('kode_jenis', 'IQRO')->first();
                if ($jenisQuran) {
                    \App\Models\WakafBatch::create([
                        'batch_code' => 'WB-'.date('Y').'-'.str_pad(\App\Models\WakafBatch::count() + 1, 5, '0', STR_PAD_LEFT),
                        'donatur_id' => $donatur->id,
                        'jenis_quran_id' => $jenisQuran->id,
                        'total_quran' => $donatur->total_iqra_count,
                        'tanggal_wakaf' => $donatur->donation_date ?? now(),
                        'status' => 'pending_distribution',
                        'created_by' => auth()->id() ?? 1,
                    ]);
                }
            }
        }
    }

    /**
     * Show certificate details
     */
    public function show($id)
    {
        auth()->user()->can(PermissionEnum::CERTIFICATES_READ->value);

        $sertifikat = Sertifikat::with(['wakafBatch.donatur', 'wakafBatch.jenisQuran', 'generator', 'donatur'])->findOrFail($id);

        // Get active jobs related to this certificate if any
        $activeJobs = JobProgress::active()
            ->where('metadata->wakaf_batch_id', $sertifikat->wakaf_batch_id)
            ->orWhere('metadata->donatur_id', $sertifikat->donatur_id)
            ->orWhere('metadata->certificate_id', $sertifikat->id)
            ->get();

        return Inertia::render('Admin/Certificates/Show', [
            'certificate' => $sertifikat,
            'wakafBatch' => $sertifikat->wakafBatch,
            'activeJobs' => $activeJobs,
        ]);
    }

    /**
     * Delete certificate
     */
    public function destroy($id)
    {
        auth()->user()->can(PermissionEnum::CERTIFICATES_DELETE->value);

        try {
            $sertifikat = Sertifikat::findOrFail($id);
            $certificateNumber = $sertifikat->nomor_sertifikat;

            // Since we're using on-demand generation, just delete the record
            $sertifikat->delete();

            return back()->with('success', "Sertifikat {$certificateNumber} berhasil dihapus.");

        } catch (Exception $e) {
            return back()->with('error', 'Gagal menghapus sertifikat: '.$e->getMessage());
        }
    }

    /**
     * Mark certificate as sent
     */
    public function markAsSent($id)
    {
        auth()->user()->can(PermissionEnum::CERTIFICATES_UPDATE->value);

        try {
            $sertifikat = Sertifikat::findOrFail($id);
            $certificateNumber = $sertifikat->nomor_sertifikat;
            $sertifikat->markAsSent();

            return back()->with('success', "Sertifikat {$certificateNumber} berhasil ditandai sebagai terkirim.");

        } catch (Exception $e) {
            return back()->with('error', 'Gagal menandai sertifikat: '.$e->getMessage());
        }
    }

    /**
     * Generate batch certificate with multiple wakif (now uses background jobs)
     */
    public function generateBatchCertificate(Request $request, WakafBatch $wakafBatch)
    {
        auth()->user()->can(PermissionEnum::CERTIFICATES_GENERATE->value);

        $request->validate([
            'template_id' => 'nullable|exists:certificate_templates,id',
            'regenerate' => 'boolean',
            'async' => 'boolean',
        ]);

        try {
            $options = [
                'template_id' => $request->input('template_id'),
                'regenerate' => $request->boolean('regenerate', false),
            ];

            // Check if should process async
            $forceAsync = $request->boolean('async', true); // Default to async for batch certificates
            $shouldProcessAsync = $forceAsync || $this->shouldProcessAsync($wakafBatch);

            if ($shouldProcessAsync) {
                // Dispatch to background job
                $job = GenerateSingleCertificateJob::dispatch(
                    $wakafBatch->id,
                    $options,
                    auth()->id()
                );

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Sertifikat batch sedang diproses di background',
                        'job_dispatched' => true,
                        'data' => [
                            'wakaf_batch_id' => $wakafBatch->id,
                            'batch_code' => $wakafBatch->batch_code,
                            'job_status_url' => route('api.jobs.active'),
                            'processing_message' => 'Anda akan menerima notifikasi setelah sertifikat selesai dibuat.',
                        ],
                    ]);
                }

                return back()->with('success', 'Sertifikat batch sedang diproses di background. Anda akan menerima notifikasi setelah selesai.');
            } else {
                // Process immediately for small batches
                $sertifikat = $this->batchCertificateService->generateBatchCertificate($wakafBatch, $options);

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Sertifikat batch berhasil dibuat',
                        'data' => [
                            'certificate_id' => $sertifikat->id,
                            'certificate_number' => $sertifikat->nomor_sertifikat,
                            'download_url' => route('admin.certificates.download', $sertifikat->id),
                        ],
                    ]);
                }

                return back()->with('success', 'Sertifikat batch berhasil dibuat');
            }

        } catch (Exception $e) {
            Log::error('Failed to generate batch certificate', [
                'wakaf_batch_id' => $wakafBatch->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal membuat sertifikat batch: '.$e->getMessage(),
                ], 422);
            }

            return back()->with('error', 'Gagal membuat sertifikat batch: '.$e->getMessage());
        }
    }

    /**
     * Get batches ready for certificate generation
     */
    public function getBatchesReadyForCertificate(Request $request)
    {
        auth()->user()->can(PermissionEnum::CERTIFICATES_READ->value);

        try {
            $readyBatches = $this->batchCertificateService->getBatchesReadyForCertificate();

            $batchesData = $readyBatches->map(function ($batch) {
                // Get wakif names from wakaf items
                $wakifItems = $this->batchCertificateService->getWakifItemsForBatch($batch);
                $wakifNames = $wakifItems->pluck('wakif_name')->unique()->values();

                // Display multiple wakif names or fallback to donatur name
                if ($wakifNames->isNotEmpty()) {
                    $wakifCount = $wakifNames->count();
                    if ($wakifCount > 3) {
                        // Show first 2 names + "... and X more"
                        $displayName = $wakifNames->take(2)->implode(', ').'... +'.($wakifCount - 2).' lainnya';
                    } else {
                        // Show all names
                        $displayName = $wakifNames->implode(', ');
                    }
                } else {
                    $displayName = $batch->donatur->nama_donatur;
                }

                return [
                    'id' => $batch->id,
                    'batch_code' => $batch->batch_code,
                    'wakif_name' => $displayName,
                    'wakif_names_full' => $wakifNames->isNotEmpty() ? $wakifNames->implode(', ') : $batch->donatur->nama_donatur,
                    'wakif_count' => $wakifNames->count(),
                    'total_quran' => $batch->total_quran,
                    'tanggal_wakaf' => $batch->tanggal_wakaf->format('d M Y'),
                    'pengiriman_count' => $batch->pengiriman()->count(),
                    'status' => $batch->status,
                    'processing_complexity' => $this->getProcessingComplexity($batch),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $batchesData,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data batch: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate bulk certificates for multiple batches (background job only)
     */
    public function generateBulkCertificates(Request $request)
    {
        auth()->user()->can(PermissionEnum::CERTIFICATES_GENERATE->value);

        $request->validate([
            'wakaf_batch_ids' => 'required|array|min:1|max:100',
            'wakaf_batch_ids.*' => 'exists:wakaf_batches,id',
            'template_id' => 'nullable|exists:certificate_templates,id',
            'regenerate' => 'boolean',
            'create_zip' => 'boolean',
        ]);

        try {
            $batchIds = $request->input('wakaf_batch_ids');
            $options = [
                'template_id' => $request->input('template_id'),
                'regenerate' => $request->boolean('regenerate', false),
                'create_zip' => $request->boolean('create_zip', true),
            ];

            // Always process bulk operations in background
            $job = GenerateBulkCertificatesJob::dispatch(
                $batchIds,
                $options,
                auth()->id()
            );

            Log::info('Bulk certificate generation job dispatched', [
                'batch_count' => count($batchIds),
                'user_id' => auth()->id(),
                'create_zip' => $options['create_zip'],
            ]);

            return response()->json([
                'success' => true,
                'message' => count($batchIds).' sertifikat sedang diproses di background',
                'job_dispatched' => true,
                'data' => [
                    'batch_count' => count($batchIds),
                    'job_status_url' => route('api.jobs.active'),
                    'will_create_zip' => $options['create_zip'],
                    'processing_message' => 'Anda akan menerima notifikasi setelah semua sertifikat selesai dibuat.'.
                                          ($options['create_zip'] ? ' File ZIP akan tersedia untuk diunduh.' : ''),
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Failed to dispatch bulk certificate generation', [
                'batch_ids' => $request->input('wakaf_batch_ids', []),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses bulk sertifikat: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate consolidated certificates for multiple donatur (background job only)
     */
    public function generateConsolidatedCertificates(Request $request)
    {
        auth()->user()->can(PermissionEnum::CERTIFICATES_GENERATE->value);

        $request->validate([
            'donatur_ids' => 'required|array|min:1|max:50',
            'donatur_ids.*' => 'exists:donaturs,id',
            'template_id' => 'nullable|exists:certificate_templates,id',
            'create_zip' => 'boolean',
        ]);

        try {
            $donaturIds = $request->input('donatur_ids');
            $options = [
                'template_id' => $request->input('template_id'),
                'create_zip' => $request->boolean('create_zip', true),
                'certificate_type' => 'consolidated',
            ];

            // Always process consolidated certificates in background
            $job = CertificateGenerationJob::dispatch(
                $donaturIds,
                'consolidated',
                $options,
                auth()->id()
            );

            Log::info('Consolidated certificate generation job dispatched', [
                'donatur_count' => count($donaturIds),
                'user_id' => auth()->id(),
                'create_zip' => $options['create_zip'],
            ]);

            return response()->json([
                'success' => true,
                'message' => count($donaturIds).' sertifikat konsolidasi sedang diproses di background',
                'job_dispatched' => true,
                'data' => [
                    'donatur_count' => count($donaturIds),
                    'job_status_url' => route('api.jobs.active'),
                    'will_create_zip' => $options['create_zip'],
                    'processing_message' => 'Anda akan menerima notifikasi setelah semua sertifikat konsolidasi selesai dibuat.'.
                                          ($options['create_zip'] ? ' File ZIP akan tersedia untuk diunduh.' : ''),
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Failed to dispatch consolidated certificate generation', [
                'donatur_ids' => $request->input('donatur_ids', []),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses sertifikat konsolidasi: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Determine if certificate processing should be async based on complexity
     */
    private function shouldProcessAsync(WakafBatch $wakafBatch): bool
    {
        // Get complexity factors
        $wakifCount = $this->batchCertificateService->getWakifItemsForBatch($wakafBatch)->groupBy('wakif_name')->count();
        $pengirimanCount = $wakafBatch->pengiriman()->count();
        $hasCustomTemplate = ! empty($wakafBatch->template_id);

        // Process async if:
        // - More than 5 wakif names
        // - More than 10 pengiriman records
        // - Custom template (might be slower)
        return $wakifCount > 5 || $pengirimanCount > 10 || $hasCustomTemplate;
    }

    /**
     * Get processing complexity score for a batch
     */
    private function getProcessingComplexity(WakafBatch $wakafBatch): string
    {
        $wakifCount = $this->batchCertificateService->getWakifItemsForBatch($wakafBatch)->groupBy('wakif_name')->count();
        $pengirimanCount = $wakafBatch->pengiriman()->count();

        $score = $wakifCount + ($pengirimanCount * 0.5);

        if ($score <= 2) {
            return 'simple';
        } elseif ($score <= 8) {
            return 'medium';
        } else {
            return 'complex';
        }
    }
}
