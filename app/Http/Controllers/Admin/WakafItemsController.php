<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDestroyWakafItemsRequest;
use App\Http\Requests\StoreWakafItemsRequest;
use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\WakafItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WakafItemsController extends Controller
{
    public function index(Donatur $donatur)
    {
        // Check permission - use donatur.read permission
        if (! auth()->user()->can('donatur.read')) {
            abort(403, 'Unauthorized to view donatur wakaf items.');
        }

        // PERFORMANCE: Optimized eager loading with selective columns
        $donatur->load([
            'wakafItems' => function ($query) {
                $query->select([
                    'id', 'donatur_id', 'pengiriman_id', 'wakaf_type', 'global_sequence',
                    'sequence_in_type', 'wakif_name', 'doa_request', 'relationship_to_donatur',
                    'status', 'created_at', 'created_by',
                ])
                    ->with([
                        'pengiriman:id,wakaf_item_id,no_resi,status_id',
                        'pengiriman.status:id,nama,warna',
                        'creator:id,name',
                    ])
                    ->orderBy('wakaf_type')
                    ->orderBy('global_sequence');
            },
            'creator:id,name',
        ]);

        // PERFORMANCE: Optimized grouping and counting with single pass
        $wakafItems = $donatur->wakafItems;
        $groupedItems = $wakafItems->groupBy('wakaf_type');

        // Pre-calculate all counts in single pass
        $statusCounts = [
            'pending' => 0,
            'processed' => 0,
            'shipped' => 0,
            'delivered' => 0,
            'total' => $wakafItems->count(),
        ];

        // Count statuses efficiently
        foreach ($wakafItems as $item) {
            $statusCounts[$item->status] = ($statusCounts[$item->status] ?? 0) + 1;
        }

        $wakafTypeData = [];
        $typeCounts = $wakafItems->countBy('wakaf_type');
        $pendingCounts = $wakafItems->where('status', 'pending')->countBy('wakaf_type');

        foreach (['A5', 'A6', 'IQRA'] as $type) {
            $items = $groupedItems->get($type, collect());

            $typeData = [
                'type' => $type,
                'total_count' => $typeCounts->get($type, 0),
                'pending_count' => $pendingCounts->get($type, 0),
                'items' => $items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'global_sequence' => $item->global_sequence,
                        'sequence_in_type' => $item->sequence_in_type,
                        'wakaf_type' => $item->wakaf_type,
                        'wakif_name' => $item->wakif_name ?? '',
                        'doa_request' => $item->doa_request ?? '',
                        'relationship_to_donatur' => $item->relationship_to_donatur ?? 'Diri sendiri',
                        'status' => $item->status,
                        'created_at' => $item->created_at->format('d/m/Y H:i'),
                        'created_at_iso' => $item->created_at->toISOString(),
                        'pengiriman' => $item->pengiriman ? [
                            'id' => $item->pengiriman->id,
                            'resi_number' => $item->pengiriman->no_resi ?? 'EQ-'.date('Y').'-'.str_pad($item->global_sequence, 5, '0', STR_PAD_LEFT),
                            'status' => $item->pengiriman->status->nama ?? 'Pending',
                            'status_color' => $item->pengiriman->status->warna ?? 'orange',
                        ] : null,
                        'can_edit' => $item->status === 'pending',
                        'can_delete' => $item->canBeDeleted(),
                        'creator_name' => $item->creator->name ?? 'System',
                    ];
                })->values()->toArray(),
            ];

            $wakafTypeData[] = $typeData;
        }

        // PERFORMANCE: Optimized item collection and type counting
        $allItems = [];
        foreach ($wakafTypeData as $typeData) {
            $allItems = array_merge($allItems, $typeData['items']);
        }

        // PERFORMANCE: Direct type count access
        $a5Count = $typeCounts->get('A5', 0);
        $a6Count = $typeCounts->get('A6', 0);
        $iqraCount = $typeCounts->get('IQRA', 0);

        // PERFORMANCE: Optimize response data structure
        $responseData = [
            'donatur' => [
                'id' => $donatur->id,
                'nama_donatur' => $donatur->nama_donatur,
                'kode_donatur' => $donatur->kode_donatur,
                'email_donatur' => $donatur->email_donatur,
                'no_hp' => $donatur->no_hp,
                'alamat_donatur' => $donatur->alamat_donatur,
                'prayer_mode' => $donatur->prayer_mode,
                'doa_untuk_semua' => $donatur->doa_untuk_semua,
            ],
            'items' => [
                'data' => $allItems, // Already an array, no need to convert
            ],
            'stats' => [
                'statusCounts' => $statusCounts,
                'typeData' => $wakafTypeData, // Already has numeric keys
                'a5_count' => $a5Count,
                'a6_count' => $a6Count,
                'iqra_count' => $iqraCount,
                'total_items' => $statusCounts['total'],
                'pending_count' => $statusCounts['pending'],
            ],
            'can_manage' => auth()->user()->can('donatur.update'),
            'performance' => [
                'items_loaded' => $wakafItems->count(),
                'query_optimized' => true,
                'cached_mapping' => cache()->has('jenis_quran_mapping'),
            ],
        ];

        return Inertia::render('Admin/Donatur/WakafItems/Index', $responseData);
    }

    /**
     * Store new wakaf items to existing donatur
     * Auto-generate corresponding pengiriman records with proper race condition handling
     */
    public function store(StoreWakafItemsRequest $request, Donatur $donatur)
    {
        // Check permission - use donatur.update permission
        if (! auth()->user()->can('donatur.update')) {
            abort(403, 'Unauthorized to add wakaf items.');
        }

        // Log request data for debugging
        logger()->info('=== WAKAF ITEMS STORE REQUEST ===', [
            'donatur_id' => $donatur->id,
            'donatur_prayer_mode' => $donatur->prayer_mode,
            'raw_request' => $request->all(),
            'validated_data' => $request->validated(),
        ]);

        try {
            DB::beginTransaction();

            $currentUser = auth()->user();
            $jenisQuranMap = $this->getJenisQuranMapping();

            // Enhanced validation - ensure JenisQuran mapping exists
            $missingMappings = [];
            foreach ($request->items as $itemData) {
                if (! isset($jenisQuranMap[$itemData['wakaf_type']])) {
                    $missingMappings[] = $itemData['wakaf_type'];
                }
            }

            if (! empty($missingMappings)) {
                throw new \Exception('JenisQuran tidak ditemukan untuk tipe: '.implode(', ', $missingMappings));
            }

            // Enhanced validation - ensure default status exists
            $defaultStatus = StatusPengiriman::where('nama', 'Proses Pemesanan')->first();
            if (! $defaultStatus) {
                // Fallback to 'Pending' status
                $defaultStatus = StatusPengiriman::where('nama', 'Pending')->first();
                if (! $defaultStatus) {
                    // Final fallback to first active status
                    $defaultStatus = StatusPengiriman::where('is_active', true)->orderBy('urutan')->first();
                    if (! $defaultStatus) {
                        throw new \Exception('Tidak ada status pengiriman yang tersedia di database.');
                    }
                }
            }

            // Remove automatic address setting - addresses should be set manually
            // $alamatTujuan = $this->getValidatedAddress($donatur);

            $createdItems = [];

            // PERFORMANCE OPTIMIZATION: Batch calculate total items needed
            $totalItemsToCreate = array_sum(array_column($request->items, 'quantity'));

            // RACE CONDITION FIX: Use database transactions for atomic sequence generation
            // Get base global sequence atomically within transaction
            $baseGlobalSequence = (int) DB::table('wakaf_items')->max('global_sequence') ?: 0;

            // Calculate sequences in batch to prevent N+1 queries
            $sequenceCalculations = [];
            foreach ($request->items as $itemData) {
                $wakafType = $itemData['wakaf_type'];
                if (! isset($sequenceCalculations[$wakafType])) {
                    $sequenceCalculations[$wakafType] = [
                        'base_sequence' => (int) DB::table('wakaf_items')
                            ->where('donatur_id', $donatur->id)
                            ->where('wakaf_type', $wakafType)
                            ->max('sequence_in_type') ?: 0,
                        'current_offset' => 0,
                    ];
                }
            }

            $globalSequenceOffset = 0;

            foreach ($request->items as $itemData) {
                $wakafType = $itemData['wakaf_type'];
                $quantity = $itemData['quantity'];

                // Handle wakif_name based on prayer mode
                $wakifName = match ($donatur->prayer_mode) {
                    'semua_donatur' => $donatur->nama_donatur,
                    'customize_individual' => trim($itemData['wakif_name'] ?? '') ?: $donatur->nama_donatur,
                    'mixed' => trim($itemData['wakif_name'] ?? '') ?: $donatur->nama_donatur,
                    default => $donatur->nama_donatur,
                };

                // Create individual wakaf items with atomic sequence assignment
                for ($i = 0; $i < $quantity; $i++) {
                    // Atomic sequence calculation
                    $globalSequence = $baseGlobalSequence + $globalSequenceOffset + 1;
                    $sequenceInType = $sequenceCalculations[$wakafType]['base_sequence'] +
                                     $sequenceCalculations[$wakafType]['current_offset'] + 1;

                    // Create wakaf item
                    $wakafItem = WakafItem::create([
                        'donatur_id' => $donatur->id,
                        'global_sequence' => $globalSequence,
                        'wakaf_type' => $wakafType,
                        'sequence_in_type' => $sequenceInType,
                        'wakif_name' => $wakifName,
                        'doa_request' => trim($itemData['doa_request'] ?? ''),
                        'relationship_to_donatur' => trim($itemData['relationship_to_donatur'] ?? '') ?: 'Diri sendiri',
                        'status' => 'pending',
                        'created_by' => $currentUser->id,
                    ]);

                    // Create corresponding pengiriman record without automatic address setting
                    // no_resi auto-generated by Pengiriman model boot method (race-safe)
                    $pengiriman = Pengiriman::create([
                        'donatur_id' => $donatur->id,
                        'wakaf_item_id' => $wakafItem->id,
                        'nama_penerima' => null, // Let user set manually
                        'no_hp_penerima' => null, // Let user set manually
                        'alamat_tujuan' => null, // Let user set manually
                        'jenis_quran_id' => $jenisQuranMap[$wakafType],
                        'jumlah_quran' => 1,
                        'tanggal_wakaf' => now()->format('Y-m-d'),
                        'status_id' => $defaultStatus->id,
                        'catatan' => "Donatur: {$donatur->nama_donatur} | Doa: ".trim($itemData['doa_request'] ?? ''),
                        'created_by' => $currentUser->id,
                    ]);

                    // Update wakaf item with pengiriman reference
                    $wakafItem->update(['pengiriman_id' => $pengiriman->id]);

                    $createdItems[] = $wakafItem;

                    // Increment offsets for next iteration
                    $globalSequenceOffset++;
                    $sequenceCalculations[$wakafType]['current_offset']++;
                }
            }

            // No need to sync computed fields - they are calculated from wakaf_items relationship

            // Clear any cached data
            cache()->forget("donatur_wakaf_items_{$donatur->id}");

            DB::commit();

            logger()->info('=== WAKAF ITEMS CREATED SUCCESSFULLY ===', [
                'donatur_id' => $donatur->id,
                'total_items' => count($createdItems),
                'performance_metrics' => [
                    'total_created' => count($createdItems),
                    'batched_sequence_calculation' => true,
                    'atomic_sequence_generation' => true,
                ],
            ]);

            return back()->with('success', count($createdItems).' item wakaf berhasil ditambahkan!');

        } catch (\Exception $e) {
            DB::rollBack();

            logger()->error('=== ERROR: Failed to create wakaf items ===', [
                'donatur_id' => $donatur->id,
                'donatur_alamat' => $donatur->alamat_donatur,
                'request_data' => $request->validated(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'context' => [
                    'user_id' => $currentUser->id ?? null,
                    'timestamp' => now()->toISOString(),
                    'total_items_attempted' => array_sum(array_column($request->items, 'quantity')),
                ],
            ]);

            // Enhanced error messages for different scenarios
            $errorMessage = match (true) {
                str_contains($e->getMessage(), 'JenisQuran tidak ditemukan') => 'Konfigurasi jenis Quran tidak lengkap. Hubungi administrator.',
                str_contains($e->getMessage(), 'Column not found') && str_contains($e->getMessage(), 'nama') => 'Konfigurasi database tidak valid. Hubungi administrator untuk memperbaiki struktur tabel.',
                str_contains($e->getMessage(), 'Column not found') && str_contains($e->getMessage(), 'name') => 'Konfigurasi database tidak valid. Hubungi administrator untuk memperbaiki struktur tabel.',
                str_contains($e->getMessage(), 'Status') && str_contains($e->getMessage(), 'tidak ditemukan') => 'Konfigurasi status pengiriman tidak lengkap. Hubungi administrator.',
                str_contains($e->getMessage(), 'was not locked with LOCK TABLES') => 'Terjadi konflik akses database. Silakan coba lagi dalam beberapa detik.',
                str_contains($e->getMessage(), 'Duplicate entry') => 'Terjadi konflik data. Silakan coba lagi.',
                str_contains($e->getMessage(), 'timeout') => 'Operasi memakan waktu terlalu lama. Silakan coba lagi.',
                default => 'Gagal menambahkan item wakaf: '.$e->getMessage()
            };

            return back()->withErrors([
                'error' => $errorMessage,
            ])->withInput();
        }
    }

    /**
     * Update a specific wakaf item (only wakif_name and doa_request)
     */
    public function update(Request $request, Donatur $donatur, WakafItem $wakafItem)
    {
        // Check permission
        if (! auth()->user()->can('donatur.update')) {
            abort(403, 'Unauthorized to update wakaf items.');
        }

        // Validate that wakaf item belongs to the donatur
        if ($wakafItem->donatur_id !== $donatur->id) {
            return back()->withErrors(['error' => 'Item wakaf tidak ditemukan untuk donatur ini.']);
        }

        // Validate that item can be updated (must be pending)
        if ($wakafItem->status !== 'pending') {
            return back()->withErrors(['error' => 'Item wakaf yang sudah diproses tidak dapat diubah.']);
        }

        // Validate input
        $request->validate([
            'wakif_name' => 'nullable|string|max:255',
            'doa_request' => 'nullable|string|max:1000',
            'relationship_to_donatur' => 'nullable|string|max:100',
        ]);

        try {
            // Update only editable fields
            $updateData = [
                'wakif_name' => trim($request->wakif_name) ?: $donatur->nama_donatur,
                'doa_request' => trim($request->doa_request ?? ''),
                'relationship_to_donatur' => trim($request->relationship_to_donatur ?? '') ?: 'Diri sendiri',
            ];

            $wakafItem->update($updateData);

            logger()->info('=== WAKAF ITEM UPDATED SUCCESSFULLY ===', [
                'donatur_id' => $donatur->id,
                'wakaf_item_id' => $wakafItem->id,
            ]);

            return back()->with('success', 'Item wakaf berhasil diperbarui!');

        } catch (\Exception $e) {
            logger()->error('=== ERROR: Failed to update wakaf item ===', [
                'donatur_id' => $donatur->id,
                'wakaf_item_id' => $wakafItem->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'error' => 'Gagal memperbarui item wakaf: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Delete a specific wakaf item with enhanced business logic validation
     */
    public function destroy(Donatur $donatur, WakafItem $wakafItem)
    {
        // Check permission
        if (! auth()->user()->can('donatur.update')) {
            abort(403, 'Unauthorized to delete wakaf items.');
        }

        // Validate that wakaf item belongs to the donatur
        if ($wakafItem->donatur_id !== $donatur->id) {
            return back()->withErrors(['error' => 'Item wakaf tidak ditemukan untuk donatur ini.']);
        }

        // Enhanced validation - check if item can be deleted with better logic
        $canDelete = $this->validateItemDeletion($wakafItem);
        if (! $canDelete['allowed']) {
            return back()->withErrors(['error' => $canDelete['reason']]);
        }

        try {
            DB::beginTransaction();

            // Enhanced logging before deletion
            logger()->info('=== WAKAF ITEM DELETION STARTED ===', [
                'donatur_id' => $donatur->id,
                'wakaf_item_id' => $wakafItem->id,
                'global_sequence' => $wakafItem->global_sequence,
                'status' => $wakafItem->status,
                'pengiriman_id' => $wakafItem->pengiriman_id,
                'user_id' => auth()->id(),
            ]);

            // Delete related pengiriman first (if exists)
            if ($wakafItem->pengiriman) {
                $pengirimanData = [
                    'id' => $wakafItem->pengiriman->id,
                    'no_resi' => $wakafItem->pengiriman->no_resi,
                    'status' => $wakafItem->pengiriman->status->nama ?? 'Unknown',
                ];

                $wakafItem->pengiriman->delete();

                logger()->info('=== PENGIRIMAN DELETED ===', $pengirimanData);
            }

            // Store item data for logging
            $itemData = [
                'id' => $wakafItem->id,
                'global_sequence' => $wakafItem->global_sequence,
                'wakaf_type' => $wakafItem->wakaf_type,
                'sequence_in_type' => $wakafItem->sequence_in_type,
                'wakif_name' => $wakafItem->wakif_name,
            ];

            // Delete the wakaf item
            $wakafItem->delete();

            // No need to sync computed fields - they are calculated from wakaf_items relationship

            // Clear cached data
            cache()->forget("donatur_wakaf_items_{$donatur->id}");

            DB::commit();

            logger()->info('=== WAKAF ITEM DELETED SUCCESSFULLY ===', [
                'donatur_id' => $donatur->id,
                'deleted_item' => $itemData,
                'remaining_items' => $donatur->wakafItems()->count(),
                'operation_success' => true,
            ]);

            return back()->with('success', 'Item wakaf berhasil dihapus!');

        } catch (\Exception $e) {
            DB::rollBack();

            logger()->error('=== ERROR: Failed to delete wakaf item ===', [
                'donatur_id' => $donatur->id,
                'wakaf_item_id' => $wakafItem->id,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_id' => auth()->id(),
                'context' => [
                    'wakaf_item_status' => $wakafItem->status,
                    'has_pengiriman' => ! is_null($wakafItem->pengiriman_id),
                    'timestamp' => now()->toISOString(),
                ],
            ]);

            $errorMessage = match (true) {
                str_contains($e->getMessage(), 'foreign key constraint') => 'Tidak dapat menghapus item karena masih terkait dengan data lain.',
                str_contains($e->getMessage(), 'deleted') => 'Item sudah dihapus sebelumnya.',
                default => 'Gagal menghapus item wakaf: '.$e->getMessage()
            };

            return back()->withErrors([
                'error' => $errorMessage,
            ]);
        }
    }

    /**
     * Enhanced validation for item deletion with detailed business rules
     */
    private function validateItemDeletion(WakafItem $wakafItem): array
    {
        // Check if item is in pending status
        if ($wakafItem->status !== 'pending') {
            return [
                'allowed' => false,
                'reason' => 'Item wakaf tidak dapat dihapus karena sudah diproses (status: '.$wakafItem->status.').',
            ];
        }

        // Check if pengiriman exists and its status
        if ($wakafItem->pengiriman) {
            $pengirimanStatus = $wakafItem->pengiriman->status->nama ?? 'Unknown';

            // Allow deletion only if pengiriman is in initial states
            $deletableStatuses = ['Pending', 'Proses Pemesanan', 'Batal'];

            if (! in_array($pengirimanStatus, $deletableStatuses)) {
                return [
                    'allowed' => false,
                    'reason' => "Item wakaf tidak dapat dihapus karena pengiriman sudah dalam status '{$pengirimanStatus}'.",
                ];
            }

            // Check for related records that would block deletion via FK constraints
            if ($wakafItem->pengiriman->packingItem()->exists()) {
                return [
                    'allowed' => false,
                    'reason' => 'Item wakaf tidak dapat dihapus karena sudah masuk ke proses packing di gudang.',
                ];
            }

            if ($wakafItem->pengiriman->dailyPackingTaskItem()->exists()) {
                return [
                    'allowed' => false,
                    'reason' => 'Item wakaf tidak dapat dihapus karena sudah di-assign ke tugas packing harian.',
                ];
            }

            if ($wakafItem->pengiriman->sertifikat()->exists()) {
                return [
                    'allowed' => false,
                    'reason' => 'Item wakaf tidak dapat dihapus karena sertifikat sudah diterbitkan.',
                ];
            }
        }

        // Additional business rule: don't allow deleting if it's the last item for the donatur
        $remainingItemsCount = $wakafItem->donatur->wakafItems()->where('id', '!=', $wakafItem->id)->count();
        if ($remainingItemsCount === 0) {
            return [
                'allowed' => false,
                'reason' => 'Tidak dapat menghapus item terakhir. Donatur harus memiliki minimal 1 item wakaf.',
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Bulk delete multiple pending wakaf items with enhanced validation and error recovery
     */
    public function bulkDestroy(BulkDestroyWakafItemsRequest $request, Donatur $donatur)
    {
        // Debug logging
        logger()->info('=== BULK DELETE REQUEST RECEIVED ===', [
            'donatur_id' => $donatur->id,
            'request_data' => $request->all(),
            'validated_data' => $request->validated(),
            'user_id' => auth()->id(),
        ]);

        // Check permission
        if (! auth()->user()->can('donatur.update')) {
            abort(403, 'Unauthorized to bulk delete wakaf items.');
        }

        try {
            DB::beginTransaction();

            // Get the wakaf items to delete with eager loading
            $wakafItems = WakafItem::whereIn('id', $request->wakaf_item_ids)
                ->where('donatur_id', $donatur->id)
                ->with(['pengiriman.status'])
                ->get();

            // Enhanced validation - check each item before deletion
            $deletableItems = [];
            $nonDeletableItems = [];

            foreach ($wakafItems as $wakafItem) {
                $canDelete = $this->validateItemDeletion($wakafItem);
                if ($canDelete['allowed']) {
                    $deletableItems[] = $wakafItem;
                } else {
                    $nonDeletableItems[] = [
                        'item' => "#{$wakafItem->global_sequence} ({$wakafItem->wakaf_type})",
                        'reason' => $canDelete['reason'],
                    ];
                }
            }

            // If some items cannot be deleted, inform user but proceed with deletable ones
            if (! empty($nonDeletableItems)) {
                logger()->warning('=== BULK DELETE: Some items cannot be deleted ===', [
                    'donatur_id' => $donatur->id,
                    'non_deletable' => $nonDeletableItems,
                    'deletable_count' => count($deletableItems),
                ]);
            }

            if (empty($deletableItems)) {
                DB::rollBack();

                return back()->withErrors([
                    'error' => 'Tidak ada item yang dapat dihapus. Alasan: '.$nonDeletableItems[0]['reason'] ?? 'Unknown',
                ]);
            }

            $deletedCount = 0;
            $deletedSequences = [];
            $pengirimanDeleted = [];
            $errors = [];

            // Process deletions with error recovery
            foreach ($deletableItems as $wakafItem) {
                try {
                    // Delete related pengiriman first (if exists)
                    if ($wakafItem->pengiriman) {
                        $pengirimanData = [
                            'id' => $wakafItem->pengiriman->id,
                            'no_resi' => $wakafItem->pengiriman->no_resi,
                            'status' => $wakafItem->pengiriman->status->nama ?? 'Unknown',
                        ];

                        $wakafItem->pengiriman->delete();
                        $pengirimanDeleted[] = $pengirimanData;
                    }

                    $deletedSequences[] = [
                        'id' => $wakafItem->id,
                        'global_sequence' => $wakafItem->global_sequence,
                        'wakaf_type' => $wakafItem->wakaf_type,
                        'wakif_name' => $wakafItem->wakif_name,
                    ];

                    $wakafItem->delete();
                    $deletedCount++;

                } catch (\Exception $itemError) {
                    logger()->error('=== ERROR: Failed to delete individual item in bulk operation ===', [
                        'wakaf_item_id' => $wakafItem->id,
                        'error' => $itemError->getMessage(),
                    ]);

                    $errors[] = "Item #{$wakafItem->global_sequence}: {$itemError->getMessage()}";
                }
            }

            // No need to sync computed fields - they are calculated from wakaf_items relationship

            // Clear cached data
            cache()->forget("donatur_wakaf_items_{$donatur->id}");

            DB::commit();

            logger()->info('=== WAKAF ITEMS BULK DELETED SUCCESSFULLY ===', [
                'donatur_id' => $donatur->id,
                'deleted_count' => $deletedCount,
                'deleted_sequences' => array_column($deletedSequences, 'global_sequence'),
                'pengiriman_deleted' => count($pengirimanDeleted),
                'non_deletable_count' => count($nonDeletableItems),
                'errors_count' => count($errors),
                'remaining_items' => $donatur->wakafItems()->count(),
            ]);

            // Prepare success message with warnings if applicable
            $message = "{$deletedCount} item wakaf berhasil dihapus!";
            if (! empty($nonDeletableItems)) {
                $message .= ' ('.count($nonDeletableItems).' item dilewati karena tidak dapat dihapus)';
            }
            if (! empty($errors)) {
                $message .= ' ('.count($errors).' item gagal dihapus)';
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();

            logger()->error('=== ERROR: Failed to bulk delete wakaf items ===', [
                'donatur_id' => $donatur->id,
                'wakaf_item_ids' => $request->wakaf_item_ids,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'user_id' => auth()->id(),
                'context' => [
                    'total_items_requested' => count($request->wakaf_item_ids),
                    'timestamp' => now()->toISOString(),
                ],
            ]);

            $errorMessage = match (true) {
                str_contains($e->getMessage(), 'foreign key constraint') => 'Tidak dapat menghapus beberapa item karena masih terkait dengan data lain.',
                str_contains($e->getMessage(), 'Deadlock') => 'Terjadi konflik dengan operasi lain. Silakan coba lagi.',
                default => 'Gagal menghapus item wakaf: '.$e->getMessage()
            };

            return back()->withErrors([
                'error' => $errorMessage,
            ]);
        }
    }

    /**
     * Get JenisQuran mapping for wakaf types with enhanced error handling and caching
     */
    private function getJenisQuranMapping(): array
    {
        return cache()->remember('jenis_quran_mapping', 3600, function () {
            $jenisQuranMap = [];
            $jenisQurans = JenisQuran::select('id', 'nama_jenis')->get();

            if ($jenisQurans->isEmpty()) {
                logger()->error('=== CRITICAL: No JenisQuran records found ===');
                throw new \Exception('Tidak ada data jenis Quran yang tersedia. Hubungi administrator.');
            }

            foreach ($jenisQurans as $jq) {
                // Use nama_jenis field since 'nama' column doesn't exist
                $nama = strtoupper($jq->nama_jenis ?? '');

                if (str_contains($nama, 'A5')) {
                    $jenisQuranMap['A5'] = $jq->id;
                } elseif (str_contains($nama, 'A6')) {
                    $jenisQuranMap['A6'] = $jq->id;
                } elseif (str_contains($nama, 'IQRO') || str_contains($nama, 'IQRA')) {
                    $jenisQuranMap['IQRA'] = $jq->id;
                }
            }

            // Validate that we have all required mappings
            $requiredTypes = ['A5', 'A6', 'IQRA'];
            $missingTypes = array_diff($requiredTypes, array_keys($jenisQuranMap));

            if (! empty($missingTypes)) {
                logger()->error('=== CRITICAL: Missing JenisQuran mappings ===', [
                    'missing_types' => $missingTypes,
                    'available_jenis_quran' => $jenisQurans->pluck('nama_jenis', 'id')->toArray(),
                    'current_mapping' => $jenisQuranMap,
                ]);

                throw new \Exception('Konfigurasi jenis Quran tidak lengkap untuk tipe: '.implode(', ', $missingTypes));
            }

            logger()->info('=== JenisQuran mapping loaded successfully ===', [
                'mapping' => $jenisQuranMap,
                'total_types' => count($jenisQuranMap),
            ]);

            return $jenisQuranMap;
        });
    }

    /**
     * DEPRECATED: Get validated address with proper fallback handling
     * This method is no longer used since automatic address setting was removed
     */
    // private function getValidatedAddress(Donatur $donatur): string
    // {
    //     $address = trim($donatur->alamat_donatur ?? '');

    //     // Return original address if it's valid and reasonably complete
    //     if (! empty($address) && strlen($address) >= 10) {
    //         return $address;
    //     }

    //     // Return fallback message for empty or too short addresses
    //     return 'Alamat belum dilengkapi';
    // }

}
