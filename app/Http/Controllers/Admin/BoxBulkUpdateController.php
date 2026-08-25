<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\BulkOperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BoxBulkUpdateController extends Controller
{
    /**
     * Parse and validate Box QR data
     * Now handles both old JSON format and new simple format
     */
    private function parseBoxQR($qrData)
    {
        try {
            // If it's a string, check if it's JSON or simple box code
            if (is_string($qrData)) {
                $decoded = json_decode($qrData, true);
                
                // Check if it's valid JSON (old format)
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    if (!isset($decoded['type']) || $decoded['type'] !== 'box') {
                        throw new \Exception('QR code is not a box type');
                    }
                    if (!isset($decoded['kode_kerdus'])) {
                        throw new \Exception('Box code missing from QR data');
                    }
                    return $decoded;
                }
                
                // New simple format - just box code
                // Validate box code format (e.g., KB-YYYYMMDD-XXX-XX-XX)
                if (preg_match('/^KB-\d{8}-\d{3}-[A-Z0-9]+-\d{2}$/', $qrData)) {
                    return ['kode_kerdus' => $qrData, 'type' => 'box'];
                }
                
                throw new \Exception('Invalid box code format');
            }
            
            // If already array (old format)
            if (is_array($qrData)) {
                if (!isset($qrData['type']) || $qrData['type'] !== 'box') {
                    throw new \Exception('QR code is not a box type');
                }
                if (!isset($qrData['kode_kerdus'])) {
                    throw new \Exception('Box code missing from QR data');
                }
                return $qrData;
            }
            
            throw new \Exception('Invalid QR data format');
        } catch (\Exception $e) {
            throw new \Exception('Failed to parse QR data: ' . $e->getMessage());
        }
    }

    /**
     * Validate Box QR and get box instance
     */
    private function validateAndGetBox($qrData)
    {
        $parsedData = $this->parseBoxQR($qrData);
        
        $box = PackingBox::where('kode_kerdus', $parsedData['kode_kerdus'])
            ->with(['packingItems.pengiriman', 'dailyPackingTask.user', 'jenisQuran'])
            ->first();
            
        if (!$box) {
            throw new \Exception('Box not found: ' . $parsedData['kode_kerdus']);
        }
        
        // Validate seal code if box is sealed
        if ($box->seal_code && isset($parsedData['seal_code'])) {
            if ($box->seal_code !== $parsedData['seal_code']) {
                throw new \Exception('Invalid seal code. Box may have been tampered with.');
            }
        }
        
        return $box;
    }

    /**
     * Get box preview with items that will be affected
     */
    public function preview(Request $request)
    {
        $request->validate([
            'qr_data' => 'required'
        ]);

        try {
            $box = $this->validateAndGetBox($request->qr_data);
            $pengirimanIds = $box->packingItems->pluck('pengiriman_id');
            
            $pengiriman = Pengiriman::whereIn('id', $pengirimanIds)
                ->with(['donatur', 'jenisQuran', 'wakafItem', 'status'])
                ->get();

            $boxInfo = [
                'kode_kerdus' => $box->kode_kerdus,
                'status' => $box->status,
                'jenis_quran' => $box->jenisQuran?->nama_jenis ?? 'N/A',
                'item_count' => $pengirimanIds->count(),
                'user_name' => $box->dailyPackingTask?->user?->name ?? 'N/A',
                'seal_code' => $box->seal_code,
                'sealed_at' => $box->sealed_at?->format('d/m/Y H:i')
            ];

            $items = $pengiriman->map(function($item) {
                return [
                    'id' => $item->id,
                    'no_resi' => $item->no_resi,
                    'donatur' => $item->donatur?->nama_donatur ?? 'N/A',
                    'wakif' => $item->wakafItem?->wakif_name ?? $item->donatur?->nama_donatur ?? 'N/A',
                    'jenis_quran' => $item->jenisQuran?->nama_jenis ?? 'N/A',
                    'current_status' => $item->status?->nama ?? 'N/A',
                    'alamat_tujuan' => $item->alamat_tujuan
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'box' => $boxInfo,
                    'items' => $items,
                    'pengiriman_ids' => $pengirimanIds
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Bulk update status for all items in box
     */
    public function bulkUpdateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'qr_data' => 'required',
            'new_status_id' => 'required|exists:status_pengiriman,id',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $box = $this->validateAndGetBox($request->qr_data);
            $pengirimanIds = $box->packingItems->pluck('pengiriman_id');
            
            if ($pengirimanIds->isEmpty()) {
                throw new \Exception('No items found in this box');
            }

            $newStatus = StatusPengiriman::find($request->new_status_id);
            
            // Update all pengiriman in the box
            $updateData = [
                'status_id' => $request->new_status_id,
                'updated_at' => now()
            ];

            $updatedCount = Pengiriman::whereIn('id', $pengirimanIds)
                ->update($updateData);

            // Log the bulk operation
            Log::info('Bulk status update via box scan', [
                'box_code' => $box->kode_kerdus,
                'new_status' => $newStatus->nama,
                'items_updated' => $updatedCount,
                'user_id' => auth()->id(),
                'notes' => $request->notes
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updatedCount} items to status: {$newStatus->nama}",
                'data' => [
                    'box_code' => $box->kode_kerdus,
                    'updated_count' => $updatedCount,
                    'new_status' => $newStatus->nama,
                    'pengiriman_ids' => $pengirimanIds->toArray()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Bulk status update failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update address for all items in box
     */
    public function bulkUpdateAddress(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'qr_data' => 'required',
            'new_address' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $box = $this->validateAndGetBox($request->qr_data);
            $pengirimanIds = $box->packingItems->pluck('pengiriman_id');
            
            if ($pengirimanIds->isEmpty()) {
                throw new \Exception('No items found in this box');
            }

            // Update all pengiriman addresses in the box
            $updateData = [
                'alamat_tujuan' => $request->new_address,
                'updated_at' => now()
            ];

            $updatedCount = Pengiriman::whereIn('id', $pengirimanIds)
                ->update($updateData);

            // Log the bulk operation
            Log::info('Bulk address update via box scan', [
                'box_code' => $box->kode_kerdus,
                'new_address' => substr($request->new_address, 0, 100) . '...',
                'items_updated' => $updatedCount,
                'user_id' => auth()->id(),
                'notes' => $request->notes
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully updated address for {$updatedCount} items",
                'data' => [
                    'box_code' => $box->kode_kerdus,
                    'updated_count' => $updatedCount,
                    'new_address' => $request->new_address,
                    'pengiriman_ids' => $pengirimanIds->toArray()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Bulk address update failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update both status and address
     */
    public function bulkUpdateBoth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'qr_data' => 'required',
            'new_status_id' => 'nullable|exists:status_pengiriman,id',
            'new_address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!$request->new_status_id && !$request->new_address) {
            return response()->json([
                'success' => false,
                'message' => 'At least one update field (status or address) is required'
            ], 422);
        }

        try {
            $startTime = microtime(true);
            DB::beginTransaction();

            $box = $this->validateAndGetBox($request->qr_data);
            $pengirimanIds = $box->packingItems->pluck('pengiriman_id');
            
            if ($pengirimanIds->isEmpty()) {
                throw new \Exception('No items found in this box');
            }

            // Get current data for logging
            $oldPengiriman = Pengiriman::whereIn('id', $pengirimanIds)->first();
            $oldStatusId = $oldPengiriman?->status_id;
            $oldAddress = $oldPengiriman?->alamat_tujuan;

            // Build update data
            $updateData = ['updated_at' => now()];
            $updateSummary = [];
            $operationType = [];

            if ($request->new_status_id) {
                $newStatus = StatusPengiriman::find($request->new_status_id);
                $updateData['status_id'] = $request->new_status_id;
                $updateSummary[] = "status to: {$newStatus->nama}";
                $operationType[] = 'status';
            }

            if ($request->new_address) {
                $updateData['alamat_tujuan'] = $request->new_address;
                $updateSummary[] = "address";
                $operationType[] = 'address';
            }

            // Update all pengiriman in the box
            $updatedCount = Pengiriman::whereIn('id', $pengirimanIds)
                ->update($updateData);

            $processingTime = round((microtime(true) - $startTime) * 1000);

            // Log to BulkOperationLog table
            BulkOperationLog::logOperation([
                'operation_type' => count($operationType) > 1 ? 'both' : $operationType[0],
                'box_code' => $box->kode_kerdus,
                'box_seal_code' => $box->seal_code,
                'pengiriman_ids' => $pengirimanIds->toArray(),
                'old_status_id' => $oldStatusId,
                'new_status_id' => $request->new_status_id,
                'old_address' => $oldAddress,
                'new_address' => $request->new_address,
                'notes' => $request->notes,
                'qr_data' => $request->qr_data,
                'processing_time_ms' => $processingTime
            ]);

            // Legacy logging
            Log::info('Bulk combined update via box scan', [
                'box_code' => $box->kode_kerdus,
                'updates' => $updateSummary,
                'items_updated' => $updatedCount,
                'user_id' => auth()->id(),
                'notes' => $request->notes,
                'processing_time_ms' => $processingTime
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully updated " . implode(' and ', $updateSummary) . " for {$updatedCount} items",
                'data' => [
                    'box_code' => $box->kode_kerdus,
                    'updated_count' => $updatedCount,
                    'updates_applied' => $updateSummary,
                    'pengiriman_ids' => $pengirimanIds->toArray()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Bulk combined update failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available statuses for bulk update
     */
    public function getAvailableStatuses()
    {
        $statuses = StatusPengiriman::orderBy('urutan')->get(['id', 'nama', 'slug', 'deskripsi']);
        
        return response()->json([
            'success' => true,
            'data' => $statuses
        ]);
    }

    /**
     * Get bulk update history for a box
     */
    public function getBulkUpdateHistory(Request $request)
    {
        $request->validate([
            'box_code' => 'required|string'
        ]);

        try {
            $history = BulkOperationLog::with(['user', 'oldStatus', 'newStatus'])
                ->where('box_code', $request->box_code)
                ->orderBy('operation_timestamp', 'desc')
                ->get()
                ->map(function($log) {
                    return [
                        'id' => $log->id,
                        'operation_type' => $log->operation_type_display,
                        'user_name' => $log->user?->name ?? 'Unknown',
                        'items_count' => $log->items_count,
                        'operation_summary' => $log->operation_summary,
                        'timestamp' => $log->operation_timestamp->format('d/m/Y H:i:s'),
                        'processing_time' => $log->processing_time,
                        'notes' => $log->notes,
                        'details' => [
                            'old_status' => $log->oldStatus?->nama,
                            'new_status' => $log->newStatus?->nama,
                            'old_address' => $log->old_address ? substr($log->old_address, 0, 100) . '...' : null,
                            'new_address' => $log->new_address ? substr($log->new_address, 0, 100) . '...' : null,
                            'ip_address' => $log->ip_address
                        ]
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $history,
                'meta' => [
                    'total_operations' => $history->count(),
                    'total_items_affected' => $history->sum('items_count'),
                    'box_code' => $request->box_code
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get history: ' . $e->getMessage()
            ], 500);
        }
    }
}