<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\StatusHistory;
use App\Models\TrackingHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\Cache\StatusPengirimanCache;

class PengirimanTrackingController extends Controller
{
    /**
     * Show update status with documentation form
     */
    public function showUpdateStatusForm(Pengiriman $pengiriman)
    {
        $pengiriman->load(['donatur', 'jenisQuran', 'status', 'creator']);
        
        // Get next possible statuses
        $currentStatus = $pengiriman->status;
        $nextStatuses = $this->getNextPossibleStatuses($currentStatus->id);
        
        return Inertia::render('Admin/Pengiriman/UpdateStatus', [
            'pengiriman' => $pengiriman,
            'statusList' => $nextStatuses,
            'currentStatus' => $currentStatus,
            'trackingHistory' => $pengiriman->trackingHistory()
                ->with(['status', 'user'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($history) {
                    return [
                        'id' => $history->id,
                        'status' => $history->status ? [
                            'id' => $history->status->id,
                            'nama' => $history->status->nama,
                            'warna' => $history->status->warna,
                            'icon' => $history->status->icon,
                        ] : null,
                        'tanggal_update' => $history->tanggal_update->format('d/m/Y H:i'),
                        'lokasi' => $history->lokasi,
                        'keterangan' => $history->keterangan,
                        'foto_dokumentasi' => $this->getSafeFotoDokumentasi($history),
                        'petugas' => $history->user?->name,
                    ];
                }),
        ]);
    }
    
    /**
     * Update status with documentation
     */
    public function updateStatusWithDocs(Request $request, Pengiriman $pengiriman)
    {
        $request->validate([
            'status_id' => ['required', 'exists:status_pengiriman,id'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'dokumentasi' => ['nullable', 'array'],
            'dokumentasi.*' => ['file', 'image', 'mimes:jpeg,jpg,png,gif', 'max:10240'], // Max 10MB per file
        ]);

        \Log::info('Update Status Request', [
            'request_all' => $request->all(),
            'files' => $request->hasFile('dokumentasi') ? 'Yes' : 'No',
            'file_count' => $request->hasFile('dokumentasi') ? count($request->file('dokumentasi')) : 0
        ]);

        try {
            DB::beginTransaction();

            $oldStatus = $pengiriman->status_id;
            
            // Validasi perubahan status
            $validTransition = $this->validateStatusTransition($oldStatus, $request->status_id);
            if (!$validTransition['valid']) {
                return back()->withErrors([
                    'error' => 'Perubahan status tidak valid: ' . $validTransition['message']
                ])->withInput();
            }
            
            // Update status
            $pengiriman->status_id = $request->status_id;
            $pengiriman->save();

            // Process dokumentasi files
            $dokFiles = [];
            if ($request->hasFile('dokumentasi')) {
                \Log::info('Processing files...');
                foreach ($request->file('dokumentasi') as $index => $file) {
                    if ($file && $file->isValid()) {
                        // Sanitize filename for security
                        $originalName = $file->getClientOriginalName();
                        $safeBaseName = \Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
                        $extension = $file->getClientOriginalExtension();
                        
                        // Validate file extension
                        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        if (!in_array(strtolower($extension), $allowedExtensions)) {
                            \Log::warning('Invalid file extension', ['extension' => $extension, 'index' => $index]);
                            continue;
                        }
                        
                        // Validate MIME type
                        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        if (!in_array($file->getMimeType(), $allowedMimes)) {
                            \Log::warning('Invalid MIME type', ['mime' => $file->getMimeType(), 'index' => $index]);
                            continue;
                        }
                        
                        $filename = time() . '_' . $index . '_' . $safeBaseName . '.' . $extension;
                        $path = $file->storeAs('dokumentasi/' . $pengiriman->no_resi, $filename, 'public');
                        $dokFiles[] = $path;
                        \Log::info('File saved', ['path' => $path]);
                    } else {
                        \Log::warning('Invalid file at index', ['index' => $index]);
                    }
                }
            }
            
            \Log::info('Final dokFiles array', ['dokFiles' => $dokFiles]);

            // Create tracking history with documentation
            TrackingHistory::create([
                'pengiriman_id' => $pengiriman->id,
                'status_id' => $request->status_id,
                'user_id' => auth()->id(),
                'tanggal_update' => now(),
                'lokasi' => $request->lokasi,
                'keterangan' => $request->catatan,
                'foto_dokumentasi' => $dokFiles,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);

            // Create status history
            StatusHistory::create([
                'pengiriman_id' => $pengiriman->id,
                'status_from' => $oldStatus,
                'status_to' => $request->status_id,
                'catatan' => $request->catatan,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('admin.pengiriman.show', $pengiriman)
                            ->with('success', "Status pengiriman {$pengiriman->no_resi} berhasil diupdate.");

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()->withErrors([
                'error' => 'Gagal update status: ' . $e->getMessage()
            ])->withInput();
        }
    }

    /**
     * Update status batch/multiple pengiriman
     */
    public function updateBatchStatus(Request $request)
    {
        $request->validate([
            'pengiriman_ids' => ['required', 'array'],
            'pengiriman_ids.*' => ['exists:pengiriman,id'],
            'status_id' => ['required', 'exists:status_pengiriman,id'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'lokasi' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            DB::beginTransaction();

            $updated = 0;
            $failed = 0;
            $pengirimanList = Pengiriman::whereIn('id', $request->pengiriman_ids)->get();

            foreach ($pengirimanList as $pengiriman) {
                $oldStatus = $pengiriman->status_id;
                
                // Validasi transisi status
                $validTransition = $this->validateStatusTransition($oldStatus, $request->status_id);
                if (!$validTransition['valid']) {
                    $failed++;
                    continue;
                }
                
                // Update status
                $pengiriman->update(['status_id' => $request->status_id]);

                // Create tracking history
                TrackingHistory::create([
                    'pengiriman_id' => $pengiriman->id,
                    'status_id' => $request->status_id,
                    'user_id' => auth()->id(),
                    'tanggal_update' => now(),
                    'lokasi' => $request->lokasi,
                    'keterangan' => $request->catatan ?? 'Batch update status',
                ]);

                // Create status history
                StatusHistory::create([
                    'pengiriman_id' => $pengiriman->id,
                    'status_from' => $oldStatus,
                    'status_to' => $request->status_id,
                    'catatan' => $request->catatan ?? 'Batch update status',
                    'created_by' => auth()->id(),
                ]);

                $updated++;
            }

            DB::commit();

            $message = "{$updated} pengiriman berhasil diupdate statusnya.";
            if ($failed > 0) {
                $message .= " {$failed} pengiriman gagal diupdate karena perubahan status tidak valid.";
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()->withErrors([
                'error' => 'Gagal bulk update: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Validate status transition
     */
    private function validateStatusTransition($currentStatusId, $newStatusId)
    {
        // Selalu boleh mengubah ke status Batal
        $newStatus = StatusPengiriman::findOrFail($newStatusId);
        if ($newStatus->slug === 'batal') {
            return [
                'valid' => true,
                'message' => 'Status dapat dibatalkan kapan saja'
            ];
        }
        
        // Jika status sama, tidak perlu update
        if ($currentStatusId == $newStatusId) {
            return [
                'valid' => false,
                'message' => 'Status sudah sama, tidak perlu diupdate'
            ];
        }
        
        $currentStatus = StatusPengiriman::findOrFail($currentStatusId);
        
        // Jika status lama adalah status final, tidak bisa diubah
        if ($currentStatus->is_final && $currentStatus->slug !== 'batal') {
            return [
                'valid' => false,
                'message' => 'Status sudah final, tidak bisa diubah'
            ];
        }
        
        // Cek urutan status, hanya boleh maju ke status selanjutnya
        if ($newStatus->urutan !== $currentStatus->urutan + 1) {
            return [
                'valid' => false,
                'message' => 'Status hanya bisa berubah ke status selanjutnya dalam urutan'
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Perubahan status valid'
        ];
    }
    
    /**
     * Get next possible statuses
     */
    private function getNextPossibleStatuses($currentStatusId)
    {
        $currentStatus = StatusPengiriman::findOrFail($currentStatusId);
        
        // If status is already final (except 'batal'), show final message
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
                    'disabled' => true
                ]
            ]);
        }
        
        // Get statuses with next order
        $nextStatuses = StatusPengiriman::where('urutan', $currentStatus->urutan + 1)
            ->where('is_active', true)
            ->get();
            
        // Always add 'Batal' status as option
        $batalStatus = StatusPengiriman::where('slug', 'batal')->first();
        if ($batalStatus && $currentStatus->slug !== 'batal') {
            $nextStatuses->push($batalStatus);
        }
        
        // Map data to add icon and description
        return $nextStatuses->map(function($status) {
            return [
                'id' => $status->id,
                'nama' => $status->nama,
                'slug' => $status->slug,
                'deskripsi' => $status->deskripsi,
                'warna' => $status->warna,
                'urutan' => $status->urutan,
                'icon' => $status->icon, // This will use the getIconAttribute method
                'is_final' => $status->is_final,
                'badge_class' => $status->badge_class // This will use the getBadgeClassAttribute method
            ];
        });
    }
    
    /**
     * Safely get foto dokumentasi URLs
     */
    private function getSafeFotoDokumentasi($history)
    {
        try {
            $fotoDokumentasi = $history->foto_dokumentasi;
            
            // If null or empty, return empty array
            if (!$fotoDokumentasi) {
                return [];
            }
            
            // If it's a string, try to decode as JSON
            if (is_string($fotoDokumentasi)) {
                $decoded = json_decode($fotoDokumentasi, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $fotoDokumentasi = $decoded;
                } else {
                    // If not valid JSON, treat as single file path
                    $fotoDokumentasi = [$fotoDokumentasi];
                }
            }
            
            // Ensure it's an array
            if (!is_array($fotoDokumentasi)) {
                return [];
            }
            
            // Process each item
            return array_map(function($item) {
                // Handle different formats
                if (is_array($item) && isset($item['path'])) {
                    return asset('storage/' . $item['path']);
                }
                if (is_object($item) && isset($item->path)) {
                    return asset('storage/' . $item->path);
                }
                if (is_string($item)) {
                    // Skip if already a full URL
                    if (str_starts_with($item, 'http')) {
                        return $item;
                    }
                    // Skip if already starts with storage/
                    if (str_starts_with($item, 'storage/')) {
                        return asset($item);
                    }
                    return asset('storage/' . $item);
                }
                // Convert any other type to string and use as path
                return asset('storage/' . (string)$item);
            }, $fotoDokumentasi);
            
        } catch (\Exception $e) {
            \Log::error('Error processing foto_dokumentasi', [
                'history_id' => $history->id ?? 'unknown',
                'foto_data' => $history->foto_dokumentasi ?? 'null',
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Update status untuk proses wakaf quran
     */
    public function updateProcessStatus(Request $request, Pengiriman $pengiriman)
    {
        $request->validate([
            'proses' => ['required', 'string', 'in:pemesanan,produksi,kedatangan,packing,dokumentasi,pengiriman,diterima'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'dokumentasi' => ['nullable', 'array'],
            'dokumentasi.*' => ['file', 'image', 'max:10240'], // Max 10MB per file
        ]);

        try {
            DB::beginTransaction();

            // Dapatkan status ID berdasarkan proses
            $status = StatusPengiriman::where('slug', $request->proses)->firstOrFail();
            $oldStatus = $pengiriman->status_id;
            
            // Update status
            $pengiriman->update(['status_id' => $status->id]);

            // Process dokumentasi files
            $dokFiles = [];
            if ($request->hasFile('dokumentasi')) {
                foreach ($request->file('dokumentasi') as $file) {
                    $path = $file->store('dokumentasi/' . $pengiriman->no_resi, 'public');
                    $dokFiles[] = $path;
                }
            }

            // Create tracking history with documentation
            TrackingHistory::create([
                'pengiriman_id' => $pengiriman->id,
                'status_id' => $status->id,
                'user_id' => auth()->id(),
                'tanggal_update' => now(),
                'keterangan' => $request->catatan,
                'foto_dokumentasi' => $dokFiles,
            ]);

            // Create status history
            StatusHistory::create([
                'pengiriman_id' => $pengiriman->id,
                'status_from' => $oldStatus,
                'status_to' => $status->id,
                'catatan' => $request->catatan,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('admin.pengiriman.show', $pengiriman)
                            ->with('success', "Status pengiriman {$pengiriman->no_resi} berhasil diupdate ke {$status->nama}.");

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()->withErrors([
                'error' => 'Gagal update status: ' . $e->getMessage()
            ])->withInput();
        }
    }
}
