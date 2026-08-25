<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDonaturRequest;
use App\Http\Requests\UpdateDonaturRequest;
use App\Http\Requests\UpdateWakifNamesRequest;
use App\Imports\DonaturImport;
use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\Pengiriman;
use App\Models\WakafBatch;
use App\Models\WakafItem;
use App\Services\OnDemandCertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class DonaturController extends Controller
{
    protected $onDemandCertificateService;

    public function __construct(OnDemandCertificateService $onDemandCertificateService)
    {
        $this->onDemandCertificateService = $onDemandCertificateService;
        $this->authorizeResource(Donatur::class, 'donatur');
    }

    /**
     * Helper method to monitor consistency between stored and actual counts
     * Used for debugging data synchronization issues
     */
    private function validateCountConsistency(Donatur $donatur): array
    {
        return [
            'a5_stored' => $donatur->getOriginal('total_a5_count'),
            'a5_actual' => $donatur->actual_a5_count,
            'a5_consistent' => $donatur->getOriginal('total_a5_count') == $donatur->actual_a5_count,
            'a6_stored' => $donatur->getOriginal('total_a6_count'),
            'a6_actual' => $donatur->actual_a6_count,
            'a6_consistent' => $donatur->getOriginal('total_a6_count') == $donatur->actual_a6_count,
            'iqra_stored' => $donatur->getOriginal('total_iqra_count'),
            'iqra_actual' => $donatur->actual_iqra_count,
            'iqra_consistent' => $donatur->getOriginal('total_iqra_count') == $donatur->actual_iqra_count,
        ];
    }

    /**
     * Search donatur by kode_donatur for autocomplete suggestions.
     */
    public function searchByKodeDonatur(Request $request)
    {
        $this->authorize('viewAny', Donatur::class);

        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([
                'data' => [],
                'message' => 'Minimal 2 karakter untuk pencarian',
            ]);
        }

        $donatur = Donatur::select('id', 'kode_donatur', 'nama_donatur', 'no_hp', 'email_donatur', 'alamat_donatur')
            ->where('kode_donatur', 'like', "%{$query}%")
            ->orderBy('kode_donatur')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $donatur,
        ]);
    }

    /**
     * Display a listing of donatur.
     */
    public function index(Request $request)
    {
        // PERFORMANCE: Optimized constrained eager loading with selective columns
        $query = Donatur::select([
            'id', 'kode_donatur', 'nama_donatur', 'no_hp', 'email_donatur',
            'alamat_donatur', 'donation_date', 'donation_count', 'jenis_wakaf_dipilih', 'prayer_mode', 'created_at', 'created_by',
        ])
            ->with([
                'creator:id,name',
                'pengiriman' => function ($query) {
                    $query->select('id', 'donatur_id', 'jumlah_quran', 'created_at')
                        ->latest()
                        ->limit(5); // Only load recent pengiriman for performance
                },
                'wakafItems' => function ($query) {
                    $query->select('id', 'donatur_id', 'wakif_name', 'wakaf_type', 'status')
                        ->where('status', 'pending') // Only load pending items for listing
                        ->limit(10); // Limit for performance
                },
            ])
            ->withCount(['wakafItems']);

        // Search functionality
        if ($request->search) {
            $query->byDonatur($request->search);
        }

        // Kode donatur filter
        if ($request->kode_donatur) {
            $query->where('kode_donatur', 'like', '%'.$request->kode_donatur.'%');
        }

        // Date filtering
        if ($request->start_date) {
            $query->whereDate('donation_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('donation_date', '<=', $request->end_date);
        }

        $donatur = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Transform the data to include actual counts from wakaf_items
        $donatur->through(function ($item) {
            // Add the actual counts as attributes
            $item->total_a5_count = $item->actual_a5_count;
            $item->total_a6_count = $item->actual_a6_count;
            $item->total_iqra_count = $item->actual_iqra_count;

            return $item;
        });

        return Inertia::render('Admin/Donatur/Index', [
            'donatur' => $donatur,
            'filters' => $request->only('search', 'kode_donatur', 'start_date', 'end_date'),
            'stats' => [
                'total_donatur' => Donatur::count(),
                'total_quran_a5' => WakafItem::where('wakaf_type', 'A5')->count(),
                'total_quran_a6' => WakafItem::where('wakaf_type', 'A6')->count(),
                'total_iqra' => WakafItem::where('wakaf_type', 'IQRA')->count(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new donatur.
     */
    public function create()
    {
        // OPTIMIZED: Select only necessary columns for jenis Quran
        $jenisQuran = JenisQuran::where('is_active', true)
            ->select('id', 'nama_jenis', 'kode_jenis', 'is_active')
            ->get();

        return Inertia::render('Admin/Donatur/Create', [
            'jenisQuran' => $jenisQuran,
        ]);
    }

    /**
     * Store a newly created donatur in storage.
     */
    public function store(StoreDonaturRequest $request)
    {
        // Validation is handled by StoreDonaturRequest

        try {
            logger()->info('=== STARTING DATABASE TRANSACTION ===');
            DB::beginTransaction();

            // Find existing donatur or create new one
            $donatur = Donatur::where('kode_donatur', $request->kode_donatur)->first();

            // Calculate quantities based on mode
            $currentA5 = 0;
            $currentA6 = 0;
            $currentIqra = 0;

            if ($request->prayer_mode === 'customize_individual' && ! empty($request->wakif_details)) {
                // Count from wakif_details
                foreach ($request->wakif_details as $detail) {
                    if ($detail['wakaf_type'] === 'A5') {
                        $currentA5++;
                    }
                    if ($detail['wakaf_type'] === 'A6') {
                        $currentA6++;
                    }
                    if ($detail['wakaf_type'] === 'IQRA') {
                        $currentIqra++;
                    }
                }
            } else {
                // Use form quantities
                $currentA5 = in_array('A5', $request->jenis_wakaf_dipilih) ? $request->jumlah_a5 : 0;
                $currentA6 = in_array('A6', $request->jenis_wakaf_dipilih) ? $request->jumlah_a6 : 0;
                $currentIqra = in_array('IQRA', $request->jenis_wakaf_dipilih) ? $request->jumlah_iqra : 0;
            }

            if ($donatur) {
                // Update existing donatur - ADD to existing quantities and INCREMENT donation count
                $donatur->update([
                    'nama_donatur' => $request->nama_donatur,
                    'no_hp' => $request->no_hp,
                    'email_donatur' => $request->email_donatur,
                    'alamat_donatur' => $request->alamat_donatur,
                    'donation_date' => $request->donation_date, // Keep latest date
                    'total_a5_count' => $donatur->total_a5_count + $currentA5,
                    'total_a6_count' => $donatur->total_a6_count + $currentA6,
                    'total_iqra_count' => $donatur->total_iqra_count + $currentIqra,
                    'donation_count' => $donatur->donation_count + 1, // INCREMENT donation count
                    'jenis_wakaf_dipilih' => array_unique(array_merge($donatur->jenis_wakaf_dipilih ?? [], $request->jenis_wakaf_dipilih)),
                    'prayer_mode' => $request->prayer_mode,
                    'doa_untuk_semua' => $request->doa_untuk_semua,
                ]);
            } else {
                // Create new donatur
                $donatur = Donatur::create([
                    'kode_donatur' => $request->kode_donatur,
                    'nama_donatur' => $request->nama_donatur,
                    'no_hp' => $request->no_hp,
                    'email_donatur' => $request->email_donatur,
                    'alamat_donatur' => $request->alamat_donatur,
                    'donation_date' => $request->donation_date,
                    'total_a5_count' => $currentA5,
                    'total_a6_count' => $currentA6,
                    'total_iqra_count' => $currentIqra,
                    'donation_count' => 1, // Start with 1 donation
                    'jenis_wakaf_dipilih' => $request->jenis_wakaf_dipilih,
                    'prayer_mode' => $request->prayer_mode,
                    'doa_untuk_semua' => $request->doa_untuk_semua,
                    'created_by' => auth()->id(),
                ]);
            }

            // Create wakaf items and track new ones
            $newWakafItems = [];

            if ($request->prayer_mode === 'customize_individual' && ! empty($request->wakif_details)) {
                // Custom individual items
                foreach ($request->wakif_details as $detail) {
                    $wakafItem = WakafItem::create([
                        'donatur_id' => $donatur->id,
                        'wakaf_type' => $detail['wakaf_type'],
                        'sequence_in_type' => $detail['sequence_in_type'],
                        'global_sequence' => $detail['global_sequence'],
                        'wakif_name' => $detail['wakif_name'],
                        'doa_request' => $detail['doa_request'],
                        'relationship_to_donatur' => $detail['relationship_to_donatur'],
                        'status' => 'pending',
                        'created_by' => auth()->id(),
                    ]);
                    $newWakafItems[] = $wakafItem;
                }
            } else {
                // Default items (use generateWakafItems which respects prayer_mode)
                $wakafItemsData = $donatur->generateWakafItems();
                foreach ($wakafItemsData as $itemData) {
                    $wakafItem = WakafItem::create($itemData);
                    $newWakafItems[] = $wakafItem;
                }
            }

            // Create pengiriman records ONLY for NEW wakaf items
            $createdResi = [];

            // ENHANCED: Dynamic JenisQuran mapping with error handling and caching
            $jenisQuranMap = cache()->remember('jenis_quran_mapping', 3600, function () {
                $jenisQuranMap = [];
                $jenisQurans = JenisQuran::select('id', 'nama_jenis')->get();

                if ($jenisQurans->isEmpty()) {
                    throw new \Exception('Tidak ada data jenis Quran yang tersedia.');
                }

                foreach ($jenisQurans as $jq) {
                    $nama = strtoupper($jq->nama_jenis ?? '');
                    if (str_contains($nama, 'A5')) {
                        $jenisQuranMap['A5'] = $jq->id;
                    } elseif (str_contains($nama, 'A6')) {
                        $jenisQuranMap['A6'] = $jq->id;
                    } elseif (str_contains($nama, 'IQRO') || str_contains($nama, 'IQRA')) {
                        $jenisQuranMap['IQRA'] = $jq->id;
                    }
                }

                // Validate mappings exist
                $requiredTypes = ['A5', 'A6', 'IQRA'];
                $missingTypes = array_diff($requiredTypes, array_keys($jenisQuranMap));
                if (! empty($missingTypes)) {
                    throw new \Exception('Konfigurasi jenis Quran tidak lengkap untuk: '.implode(', ', $missingTypes));
                }

                return $jenisQuranMap;
            });

            logger()->info('=== JENIS QURAN MAPPING LOADED ===', [
                'mapping' => $jenisQuranMap,
                'cached' => true,
            ]);

            // ENHANCED: Create pengiriman with comprehensive error handling
            foreach ($newWakafItems as $index => $wakafItem) {
                try {
                    // Validate jenis_quran_id exists
                    $jenisQuranId = $jenisQuranMap[$wakafItem->wakaf_type] ?? null;
                    if (! $jenisQuranId) {
                        throw new \Exception("JenisQuran tidak ditemukan untuk tipe: {$wakafItem->wakaf_type}");
                    }

                    // Get default status with error handling
                    $defaultStatusId = \App\Models\StatusPengiriman::getDefaultStatusId();
                    if (! $defaultStatusId) {
                        throw new \Exception('Status pengiriman default tidak ditemukan.');
                    }

                    // FIXED: Do not pre-fill address fields when creating pengiriman
                    // Address should only be filled when mushaf request is approved and assigned
                    $pengiriman = Pengiriman::create([
                        'donatur_id' => $donatur->id,
                        'wakaf_item_id' => $wakafItem->id,
                        'jenis_quran_id' => $jenisQuranId,
                        'jumlah_quran' => 1,
                        'tanggal_wakaf' => $donatur->donation_date,
                        'status_id' => $defaultStatusId,
                        // Leave address fields empty until mushaf request is approved:
                        'nama_penerima' => null,
                        'no_hp_penerima' => null,
                        'alamat_tujuan' => null,
                        'catatan' => "Donatur: {$donatur->nama_donatur} | Doa: ".($wakafItem->doa_request ?? '-'),
                        'created_by' => auth()->id(),
                    ]);

                    // Update wakaf item with pengiriman_id
                    $wakafItem->update(['pengiriman_id' => $pengiriman->id]);

                    $createdResi[] = $pengiriman->no_resi;

                    logger()->info('=== PENGIRIMAN CREATED ===', [
                        'pengiriman_id' => $pengiriman->id,
                        'wakaf_item_id' => $wakafItem->id,
                        'no_resi' => $pengiriman->no_resi,
                        'jenis_quran_id' => $jenisQuranId,
                    ]);

                } catch (\Exception $pengirimanError) {
                    logger()->error('=== ERROR: Failed to create pengiriman for wakaf item ===', [
                        'wakaf_item_id' => $wakafItem->id,
                        'index' => $index,
                        'error' => $pengirimanError->getMessage(),
                        'context' => [
                            'wakaf_type' => $wakafItem->wakaf_type,
                            'donatur_alamat' => ! empty($donatur->alamat_donatur),
                            'jenis_quran_mapped' => isset($jenisQuranMap[$wakafItem->wakaf_type]),
                        ],
                    ]);

                    throw $pengirimanError;
                }
            }

            DB::commit();

            logger()->info('=== SUCCESS: Donatur created successfully ===', [
                'donatur_id' => $donatur->id,
                'total_items' => count($newWakafItems),
            ]);

            return redirect()->route('admin.donatur.index')
                ->with('success',
                    'Donatur berhasil dibuat! '.
                    "Donatur: {$donatur->nama_donatur}, ".
                    "Total: {$donatur->total_quran} Al-Qur'an, ".
                    'Resi: '.implode(', ', array_slice($createdResi, 0, 3)).
                    (count($createdResi) > 3 ? ' dan '.(count($createdResi) - 3).' lainnya' : '')
                );

        } catch (\Exception $e) {
            DB::rollback();

            logger()->error('=== ERROR: Failed to create donatur ===', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request_data' => $request->validated(),
                'context' => [
                    'user_id' => auth()->id(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'timestamp' => now()->toISOString(),
                    'donation_mode' => $request->prayer_mode,
                    'total_items_attempted' => array_sum([
                        $request->jumlah_a5 ?? 0,
                        $request->jumlah_a6 ?? 0,
                        $request->jumlah_iqra ?? 0,
                    ]),
                ],
                'trace' => $e->getTraceAsString(),
            ]);

            // ENHANCED: Provide contextual error messages
            $errorMessage = match (true) {
                str_contains($e->getMessage(), 'jenis Quran') => 'Konfigurasi jenis Quran tidak lengkap. Hubungi administrator.',
                str_contains($e->getMessage(), 'Status pengiriman') => 'Konfigurasi status pengiriman tidak lengkap. Hubungi administrator.',
                str_contains($e->getMessage(), 'alamat lengkap') => 'Alamat donatur harus diisi dengan lengkap.',
                str_contains($e->getMessage(), 'Duplicate entry') => 'Data donatur sudah ada. Periksa kode donatur dan nomor HP.',
                str_contains($e->getMessage(), 'foreign key constraint') => 'Terjadi kesalahan referensi data. Hubungi administrator.',
                default => 'Gagal menyimpan donatur: '.$e->getMessage()
            };

            return back()->withErrors([
                'error' => $errorMessage,
            ])->withInput();
        }
    }

    /**
     * Display the specified donatur.
     */
    public function show(Donatur $donatur)
    {
        $donatur->load([
            'wakafItems.pengiriman.status',
            'wakafItems.pengiriman.jenisQuran',
            'wakafBatches.sertifikat',
            'creator',
        ]);

        // Apply same transformation as index() method for consistency
        $donatur->total_a5_count = $donatur->actual_a5_count;
        $donatur->total_a6_count = $donatur->actual_a6_count;
        $donatur->total_iqra_count = $donatur->actual_iqra_count;

        // Generate certificate download URLs for each batch
        $certificateUrls = [];
        foreach ($donatur->wakafBatches as $batch) {
            if ($batch->sertifikat) {
                $certificateUrls[$batch->id] = [
                    'batch_code' => $batch->batch_code,
                    'download_url' => $this->onDemandCertificateService->getPublicDownloadUrl($batch->sertifikat),
                    'nomor_sertifikat' => $batch->sertifikat->nomor_sertifikat,
                    'generated_at' => $batch->sertifikat->generated_at,
                ];
            }
        }

        return Inertia::render('Admin/Donatur/Show', [
            'donatur' => $donatur,
            'certificateUrls' => $certificateUrls,
        ]);
    }

    /**
     * Show the form for editing the specified donatur.
     */
    public function edit(Donatur $donatur)
    {
        // Load wakaf items with pending status for editing
        $donatur->load(['wakafItems' => function ($query) {
            $query->orderBy('wakaf_type')->orderBy('sequence_in_type');
        }]);

        // Apply same transformation as index() method for consistency
        $donatur->total_a5_count = $donatur->actual_a5_count;
        $donatur->total_a6_count = $donatur->actual_a6_count;
        $donatur->total_iqra_count = $donatur->actual_iqra_count;

        $jenisQuran = JenisQuran::where('is_active', true)->get();

        // Prepare data with computed quantities and wakaf items
        $donaturData = $donatur->toArray();

        // Add computed quantities (actual counts from wakaf_items)
        $donaturData['actualA5Count'] = $donatur->actual_a5_count;
        $donaturData['actualA6Count'] = $donatur->actual_a6_count;
        $donaturData['actualIqraCount'] = $donatur->actual_iqra_count;

        // Ensure prayer_mode is set (fallback to computed value)
        if (empty($donaturData['prayer_mode'])) {
            // Legacy data conversion - compute prayer_mode from old boolean fields
            if ($donatur->getOriginal('semua_atas_nama_donatur')) {
                $donaturData['prayer_mode'] = 'semua_donatur';
            } elseif ($donatur->getOriginal('customize_individual')) {
                $donaturData['prayer_mode'] = 'customize_individual';
            } else {
                $donaturData['prayer_mode'] = 'semua_donatur'; // default
            }
        }

        return Inertia::render('Admin/Donatur/Edit', [
            'donatur' => $donaturData,
            'wakafItems' => $donatur->wakafItems,
            'jenisQuran' => $jenisQuran,
        ]);
    }

    /**
     * Update the specified donatur in storage.
     * Only updates editable fields - quantities are computed from wakaf_items
     */
    public function update(UpdateDonaturRequest $request, Donatur $donatur)
    {
        // DEBUG: Log request data for debugging
        // Validation is handled by UpdateDonaturRequest

        try {
            DB::beginTransaction();

            // Update only editable fields - NO quantity updates
            $donatur->update([
                'kode_donatur' => $request->kode_donatur,
                'nama_donatur' => $request->nama_donatur,
                'no_hp' => $request->no_hp,
                'email_donatur' => $request->email_donatur,
                'alamat_donatur' => $request->alamat_donatur,
                'doa_untuk_semua' => $request->doa_untuk_semua,
                'prayer_mode' => $request->prayer_mode,
            ]);

            // Sync wakaf items with new prayer mode settings
            $syncedCount = $donatur->syncWakafItemsWithPrayerMode();

            logger()->info('=== PRAYER MODE SYNC COMPLETED ===', [
                'synced_items_count' => $syncedCount,
                'prayer_mode' => $request->prayer_mode,
            ]);

            DB::commit();

            logger()->info('=== DONATUR UPDATE SUCCESS ===', [
                'donatur_id' => $donatur->id,
                'updated_fields' => [
                    'nama_donatur' => $request->nama_donatur,
                    'prayer_mode' => $request->prayer_mode,
                ],
                'synced_items' => $syncedCount,
            ]);

            return redirect()->route('admin.donatur.index')
                ->with('success', "Donatur berhasil diperbarui! {$syncedCount} item wakaf telah disinkronisasi.");

        } catch (\Exception $e) {
            DB::rollback();

            logger()->error('=== ERROR: Failed to update donatur ===', [
                'donatur_id' => $donatur->id,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request_data' => $request->validated(),
                'context' => [
                    'user_id' => auth()->id(),
                    'old_prayer_mode' => $donatur->getOriginal('prayer_mode'),
                    'new_prayer_mode' => $request->prayer_mode,
                    'ip_address' => request()->ip(),
                    'timestamp' => now()->toISOString(),
                ],
                'trace' => $e->getTraceAsString(),
            ]);

            // ENHANCED: Contextual error messages
            $errorMessage = match (true) {
                str_contains($e->getMessage(), 'syncWakafItemsWithPrayerMode') => 'Gagal menyinkronkan mode doa dengan item wakaf.',
                str_contains($e->getMessage(), 'foreign key constraint') => 'Tidak dapat memperbarui karena data terkait dengan sistem lain.',
                str_contains($e->getMessage(), 'Duplicate entry') => 'Data yang diperbarui konflik dengan data existing.',
                default => 'Gagal memperbarui donatur: '.$e->getMessage()
            };

            return back()->withErrors([
                'error' => $errorMessage,
            ])->withInput();
        }
    }

    /**
     * Update wakif names for individual wakaf items
     * Only allows updating pending items that belong to the donatur
     */
    public function updateWakifNames(UpdateWakifNamesRequest $request, Donatur $donatur)
    {
        $this->authorize('update', $donatur);

        logger()->info('=== WAKIF NAMES UPDATE REQUEST ===', [
            'donatur_id' => $donatur->id,
            'request_data' => $request->validated(),
        ]);

        // Validation is handled by UpdateWakifNamesRequest

        try {
            DB::beginTransaction();

            $updatedCount = 0;
            $errors = [];

            foreach ($request->wakif_updates as $update) {
                $wakafItem = WakafItem::where('id', $update['wakaf_item_id'])
                    ->where('donatur_id', $donatur->id)
                    ->where('status', 'pending') // Only allow updating pending items
                    ->first();

                if (! $wakafItem) {
                    $errors[] = "Item wakaf dengan ID {$update['wakaf_item_id']} tidak ditemukan atau tidak dapat diubah.";

                    continue;
                }

                $wakafItem->update([
                    'wakif_name' => $update['wakif_name'],
                    'doa_request' => $update['doa_request'] ?? null,
                    'relationship_to_donatur' => $update['relationship_to_donatur'] ?? null,
                ]);

                $updatedCount++;

                logger()->info('=== WAKAF ITEM UPDATED ===', [
                    'wakaf_item_id' => $wakafItem->id,
                    'wakif_name' => $update['wakif_name'],
                ]);
            }

            if (! empty($errors)) {
                DB::rollback();

                return back()->withErrors(['wakif_updates' => implode(' ', $errors)])->withInput();
            }

            DB::commit();

            logger()->info('=== WAKIF NAMES UPDATE SUCCESS ===', [
                'donatur_id' => $donatur->id,
                'updated_count' => $updatedCount,
            ]);

            return back()->with('success', "Berhasil memperbarui {$updatedCount} nama wakif!");

        } catch (\Exception $e) {
            DB::rollback();

            logger()->error('=== ERROR: Failed to update wakif names ===', [
                'donatur_id' => $donatur->id,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request_data' => $request->validated(),
                'context' => [
                    'user_id' => auth()->id(),
                    'total_updates_attempted' => count($request->wakif_updates ?? []),
                    'successful_updates' => $updatedCount,
                    'ip_address' => request()->ip(),
                    'timestamp' => now()->toISOString(),
                ],
                'partial_success' => $updatedCount > 0,
                'trace' => $e->getTraceAsString(),
            ]);

            // ENHANCED: Contextual error messages with recovery suggestions
            $errorMessage = match (true) {
                $updatedCount > 0 => "Berhasil memperbarui {$updatedCount} nama wakif, tetapi terjadi kesalahan: ".$e->getMessage(),
                str_contains($e->getMessage(), 'not found') => 'Beberapa item wakaf tidak ditemukan atau sudah dihapus.',
                str_contains($e->getMessage(), 'pending') => 'Hanya item wakaf dengan status pending yang dapat diperbarui.',
                default => 'Gagal memperbarui nama wakif: '.$e->getMessage()
            };

            return back()->withErrors([
                'error' => $errorMessage,
            ])->withInput();
        }
    }

    /**
     * Remove the specified donatur from storage.
     */
    public function destroy(Donatur $donatur)
    {
        try {
            DB::beginTransaction();

            // Log deletion attempt
            logger()->info('=== ATTEMPTING TO DELETE DONATUR ===', [
                'donatur_id' => $donatur->id,
                'nama_donatur' => $donatur->nama_donatur,
                'wakaf_items_count' => $donatur->wakafItems()->count(),
                'pengiriman_count' => $donatur->pengiriman()->count(),
            ]);

            // Check if any pengiriman is not cancelled (only allow deletion if all are Batal)
            $nonCancelledItems = $donatur->wakafItems()
                ->whereHas('pengiriman', function ($q) {
                    $q->whereHas('status', function ($sq) {
                        $sq->where('nama', '!=', 'Batal');
                    });
                })
                ->count();

            logger()->info('Non-cancelled items count: '.$nonCancelledItems);

            if ($nonCancelledItems > 0) {
                DB::rollback();
                logger()->warning('=== DELETION BLOCKED: ACTIVE SHIPMENTS ===', [
                    'donatur_id' => $donatur->id,
                    'non_cancelled_items' => $nonCancelledItems,
                ]);

                return back()->withErrors([
                    'error' => 'Tidak dapat menghapus donatur yang memiliki pengiriman aktif. Ubah status pengiriman menjadi "Batal" terlebih dahulu.',
                ]);
            }

            // Delete related records in correct order
            $donatur->wakafBatches()->delete();
            $donatur->wakafItems()->delete();
            $donatur->pengiriman()->delete();
            $donatur->delete();

            DB::commit();

            logger()->info('=== DONATUR DELETED SUCCESSFULLY ===', [
                'donatur_id' => $donatur->id,
            ]);

            return redirect()->route('admin.donatur.index')
                ->with('success', 'Donatur berhasil dihapus!');

        } catch (\Exception $e) {
            DB::rollback();

            logger()->error('=== ERROR: Failed to delete donatur ===', [
                'donatur_id' => $donatur->id,
                'donatur_name' => $donatur->nama_donatur,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'context' => [
                    'user_id' => auth()->id(),
                    'wakaf_items_count' => $donatur->wakafItems()->count(),
                    'pengiriman_count' => $donatur->pengiriman()->count(),
                    'ip_address' => request()->ip(),
                    'timestamp' => now()->toISOString(),
                    'deletion_attempt_reason' => 'Manual deletion by user',
                ],
                'trace' => $e->getTraceAsString(),
            ]);

            // ENHANCED: Detailed error messages with guidance
            $errorMessage = match (true) {
                str_contains($e->getMessage(), 'foreign key constraint') => 'Tidak dapat menghapus donatur karena masih memiliki data terkait yang aktif.',
                str_contains($e->getMessage(), 'non-cancelled items') => 'Tidak dapat menghapus donatur yang memiliki pengiriman aktif. Batalkan pengiriman terlebih dahulu.',
                str_contains($e->getMessage(), 'Deadlock') => 'Terjadi konflik sistem. Silakan coba lagi dalam beberapa menit.',
                default => 'Gagal menghapus donatur: '.$e->getMessage()
            };

            return back()->withErrors([
                'error' => $errorMessage,
            ]);
        }
    }

    /**
     * Add Quran for existing donatur
     */
    public function addQuran(Request $request, Donatur $donatur)
    {
        $this->authorize('update', $donatur);

        $request->validate([
            'jenis_quran_id' => ['required', 'exists:jenis_quran,id'],
            'jumlah_quran' => ['required', 'integer', 'min:1', 'max:100'],
            'tanggal_wakaf' => ['required', 'date'],
            'nama_penerima' => ['nullable', 'string', 'max:255'],
            'alamat_tujuan' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
        ]);

        try {
            DB::beginTransaction();

            // Get JenisQuran for validation and type detection
            $jenisQuran = JenisQuran::findOrFail($request->jenis_quran_id);

            // Create new wakaf batch
            $wakafBatch = WakafBatch::create([
                'donatur_id' => $donatur->id,
                'batch_code' => 'WB-'.$donatur->id.'-'.($donatur->wakafBatches()->count() + 1).'-'.now()->format('Ymd'),
                'jenis_quran_id' => $request->jenis_quran_id,
                'total_quran' => $request->jumlah_quran,
                'tanggal_wakaf' => $request->tanggal_wakaf,
                'created_by' => auth()->id(),
            ]);

            // Create pengiriman records
            for ($i = 1; $i <= $request->jumlah_quran; $i++) {
                Pengiriman::create([
                    'donatur_id' => $donatur->id,
                    'wakaf_batch_id' => $wakafBatch->id,
                    'sequence_in_batch' => $i,
                    'jenis_quran_id' => $request->jenis_quran_id,
                    'jumlah_quran' => 1,
                    'tanggal_wakaf' => $request->tanggal_wakaf,
                    'status_id' => \App\Models\StatusPengiriman::getDefaultStatusId(), // Default status: Proses Pemesanan
                    'nama_penerima' => $request->nama_penerima ?: null,
                    'alamat_tujuan' => $request->alamat_tujuan,
                    'catatan' => $request->catatan,
                    'created_by' => auth()->id(),
                ]);
            }

            // FIX: Update donatur totals
            $donatur->increment('donation_count');

            // Update total counts based on jenis quran
            $jenisNama = strtoupper($jenisQuran->nama_jenis);
            if (str_contains($jenisNama, 'A5')) {
                $donatur->increment('total_a5_count', $request->jumlah_quran);
            } elseif (str_contains($jenisNama, 'A6')) {
                $donatur->increment('total_a6_count', $request->jumlah_quran);
            } elseif (str_contains($jenisNama, 'IQRA')) {
                $donatur->increment('total_iqra_count', $request->jumlah_quran);
            }

            // Update donation date to latest
            $donatur->update(['donation_date' => $request->tanggal_wakaf]);

            DB::commit();

            return back()->with('success', "Berhasil menambahkan {$request->jumlah_quran} Al-Qur'an untuk {$donatur->nama_donatur}!");

        } catch (\Exception $e) {
            DB::rollback();

            logger()->error('=== ERROR: Failed to add Quran to donatur ===', [
                'donatur_id' => $donatur->id,
                'jenis_quran_id' => $request->jenis_quran_id,
                'jumlah_quran' => $request->jumlah_quran,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'context' => [
                    'user_id' => auth()->id(),
                    'ip_address' => request()->ip(),
                    'timestamp' => now()->toISOString(),
                ],
                'trace' => $e->getTraceAsString(),
            ]);

            // ENHANCED: Contextual error messages
            $errorMessage = match (true) {
                str_contains($e->getMessage(), 'jenis_quran_id') => 'Jenis Quran yang dipilih tidak valid.',
                str_contains($e->getMessage(), 'Status pengiriman') => 'Konfigurasi status pengiriman bermasalah.',
                str_contains($e->getMessage(), 'foreign key constraint') => 'Terjadi kesalahan referensi data.',
                default => 'Gagal menambahkan Al-Qur\'an: '.$e->getMessage()
            };

            return back()->withErrors([
                'error' => $errorMessage,
            ]);
        }
    }

    /**
     * Export donatur data to Excel
     */
    public function export(Request $request)
    {
        $this->authorize('export', Donatur::class);

        try {
            $filters = $request->only(['search', 'kode_donatur', 'start_date', 'end_date']);
            $filename = 'donatur-'.now()->format('Y-m-d-H-i-s');

            if (isset($filters['kode_donatur']) && $filters['kode_donatur']) {
                $filename .= '-'.$filters['kode_donatur'];
            }

            if (isset($filters['start_date']) && $filters['start_date']) {
                $filename .= '-from-'.$filters['start_date'];
            }

            if (isset($filters['end_date']) && $filters['end_date']) {
                $filename .= '-to-'.$filters['end_date'];
            }

            $filename .= '.xlsx';

            return Excel::download(new \App\Exports\DonaturExport($filters), $filename);
        } catch (\Exception $e) {
            \Log::error('Export donatur error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'error' => 'Gagal melakukan export: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Import donatur data from Excel
     */
    public function import(Request $request)
    {
        $this->authorize('import', Donatur::class);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        try {
            $import = new DonaturImport(new \App\Services\DonaturImportService);
            Excel::import($import, $request->file('file'));

            $results = $import->getResults();

            if ($results['success_count'] === 0 && $results['error_count'] === 0) {
                return back()->with('error', 'File Excel kosong atau tidak memiliki data. Pastikan file Excel berisi data sesuai template (minimal 1 baris data setelah header).');
            }

            if ($results['error_count'] > 0) {
                return back()->with([
                    'warning' => "Import selesai dengan {$results['success_count']} data berhasil dan {$results['error_count']} data gagal",
                    'import_errors' => $results['errors'],
                ]);
            }

            return back()->with('success', "Berhasil import {$results['success_count']} data donatur");
        } catch (\Exception $e) {
            return back()->with('error', 'Error saat import: '.$e->getMessage());
        }
    }

    /**
     * Download Excel import template
     */
    public function downloadTemplate()
    {
        $this->authorize('import', Donatur::class);

        try {
            $filename = 'donatur-import-template-'.now()->format('Y-m-d').'.xlsx';

            return Excel::download(new \App\Exports\DonaturTemplateExport, $filename);
        } catch (\Exception $e) {
            return back()->with('error', 'Error saat download template: '.$e->getMessage());
        }
    }
}
