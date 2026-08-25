<?php

namespace App\Services;

use App\Models\Pengiriman;
use App\Models\Sertifikat;
use App\Models\StatusHistory;
use App\Models\TrackingHistory;
use App\Jobs\SendCertificateToWakif;
use App\Jobs\SendDeliveryNotificationToWakif;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PostDeliveryService
{
    /**
     * Handle actions when Quran is received by beneficiary
     */
    public function handleQuranReceived(Pengiriman $pengiriman, array $deliveryData = [])
    {
        try {
            DB::beginTransaction();
            
            // 1. Update pengiriman dengan data penerimaan
            $this->updateDeliveryData($pengiriman, $deliveryData);
            
            // 2. Generate sertifikat otomatis (jika belum ada)
            $sertifikat = $this->generateCertificateIfNeeded($pengiriman);
            
            // 3. Create completion tracking entry
            $this->createCompletionTracking($pengiriman, $deliveryData);
            
            // 4. Queue notifications to wakif
            $this->queueWakifNotifications($pengiriman, $sertifikat, $deliveryData);
            
            // 5. Update statistics
            $this->updateDeliveryStatistics($pengiriman);
            
            DB::commit();
            
            Log::info('Quran delivery completed successfully', [
                'no_resi' => $pengiriman->no_resi,
                'wakif_id' => $pengiriman->wakif_id,
                'certificate_generated' => $sertifikat ? true : false
            ]);
            
            return [
                'success' => true,
                'message' => 'Pengiriman berhasil diselesaikan',
                'certificate' => $sertifikat,
                'tracking_completed' => true
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error('Failed to handle Quran delivery completion', [
                'no_resi' => $pengiriman->no_resi,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Update pengiriman dengan data penerimaan
     */
    private function updateDeliveryData(Pengiriman $pengiriman, array $deliveryData)
    {
        $updateData = [
            'received_at' => now(),
        ];
        
        if (isset($deliveryData['received_by'])) {
            $updateData['received_by'] = $deliveryData['received_by'];
        }
        
        if (isset($deliveryData['receiver_contact'])) {
            $updateData['receiver_contact'] = $deliveryData['receiver_contact'];
        }
        
        if (isset($deliveryData['delivery_proof'])) {
            $updateData['delivery_proof'] = json_encode($deliveryData['delivery_proof']);
        }
        
        $pengiriman->update($updateData);
    }
    
    /**
     * Generate sertifikat jika belum ada
     */
    private function generateCertificateIfNeeded(Pengiriman $pengiriman)
    {
        // Check if certificate already exists for this batch
        $existingSertifikat = Sertifikat::where('wakaf_batch_id', $pengiriman->wakaf_batch_id)->first();
        
        if ($existingSertifikat) {
            return $existingSertifikat;
        }
        
        // Generate new certificate for the batch
        try {
            $certificateService = app(\App\Services\CertificateService::class);
            $sertifikat = $certificateService->generateForBatch($pengiriman->wakafBatch);
            
            Log::info('Certificate auto-generated for completed delivery', [
                'no_resi' => $pengiriman->no_resi,
                'certificate_id' => $sertifikat->id,
                'batch_id' => $pengiriman->wakaf_batch_id
            ]);
            
            return $sertifikat;
            
        } catch (\Exception $e) {
            Log::warning('Failed to auto-generate certificate', [
                'no_resi' => $pengiriman->no_resi,
                'batch_id' => $pengiriman->wakaf_batch_id,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Create tracking entry for completion
     */
    private function createCompletionTracking(Pengiriman $pengiriman, array $deliveryData)
    {
        $trackingData = [
            'pengiriman_id' => $pengiriman->id,
            'status_id' => $pengiriman->status_id, // "Diterima Penerima"
            'user_id' => auth()->id(),
            'tanggal_update' => now(),
            'keterangan' => 'Quran telah diterima oleh penerima manfaat',
        ];
        
        // Add delivery details
        if (isset($deliveryData['lokasi'])) {
            $trackingData['lokasi'] = $deliveryData['lokasi'];
        }
        
        if (isset($deliveryData['latitude']) && isset($deliveryData['longitude'])) {
            $trackingData['latitude'] = $deliveryData['latitude'];
            $trackingData['longitude'] = $deliveryData['longitude'];
        }
        
        if (isset($deliveryData['delivery_proof'])) {
            $trackingData['foto_dokumentasi'] = $deliveryData['delivery_proof'];
        }
        
        TrackingHistory::create($trackingData);
    }
    
    /**
     * Queue notifications to wakif
     */
    private function queueWakifNotifications(Pengiriman $pengiriman, $sertifikat, array $deliveryData)
    {
        // 1. Send delivery completion notification
        SendDeliveryNotificationToWakif::dispatch($pengiriman, $deliveryData);
        
        // 2. Send certificate (if available)
        if ($sertifikat) {
            SendCertificateToWakif::dispatch($sertifikat, $pengiriman->wakif);
        }
    }
    
    /**
     * Update delivery statistics
     */
    private function updateDeliveryStatistics(Pengiriman $pengiriman)
    {
        // Update wakif statistics
        $pengiriman->wakif->increment('total_quran_delivered', $pengiriman->jumlah_quran);
        $pengiriman->wakif->touch(); // Update updated_at
        
        // Update batch completion status if all pengiriman in batch are completed
        $this->checkBatchCompletion($pengiriman->wakafBatch);
    }
    
    /**
     * Check if entire batch is completed
     */
    private function checkBatchCompletion($wakafBatch)
    {
        if (!$wakafBatch) return;
        
        $totalPengiriman = $wakafBatch->pengiriman()->count();
        $completedPengiriman = $wakafBatch->pengiriman()
            ->whereHas('status', function($q) {
                $q->where('slug', 'diterima');
            })
            ->count();
        
        if ($totalPengiriman > 0 && $completedPengiriman === $totalPengiriman) {
            $wakafBatch->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);
            
            Log::info('Wakaf batch completed', [
                'batch_id' => $wakafBatch->id,
                'batch_code' => $wakafBatch->batch_code,
                'total_pengiriman' => $totalPengiriman
            ]);
        }
    }
}
