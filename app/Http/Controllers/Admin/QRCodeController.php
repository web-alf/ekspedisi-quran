<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengiriman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
// use Intervention\Image\ImageManager;
// use Intervention\Image\Drivers\Gd\Driver;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QRCodeController extends Controller
{
    private $storagePath = 'public/qr-codes';

    /**
     * Generate QR Code for pengiriman
     */
    public function generate(Pengiriman $pengiriman)
    {
        try {
            // Check if QR Code already exists - prevent regeneration
            if ($pengiriman->qr_code_path && Storage::exists($pengiriman->qr_code_path)) {
                if (request()->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'QR Code untuk resi '.$pengiriman->no_resi.' sudah ada. Regenerasi tidak diizinkan.',
                    ], 409); // Conflict
                }

                return back()->withErrors(['error' => 'QR Code untuk resi '.$pengiriman->no_resi.' sudah ada.']);
            }

            // Generate comprehensive QR data
            $qrData = $this->buildQRData($pengiriman);

            // Generate QR Code image
            $qrCodePath = $this->generateQRImage($pengiriman, $qrData);

            // Update pengiriman record
            $pengiriman->update([
                'qr_code_path' => $qrCodePath,
                'qr_code_data' => $qrData, // Store as plain string
            ]);

            // Check if this is an AJAX request
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'QR Code untuk resi '.$pengiriman->no_resi.' berhasil dibuat.',
                    'data' => [
                        'qr_url' => Storage::url($qrCodePath),
                        'qr_code_path' => Storage::url($qrCodePath),
                        'qr_data' => $qrData,
                        'no_resi' => $pengiriman->no_resi,
                    ],
                ]);
            }

            return back()->with('success', 'QR Code untuk resi '.$pengiriman->no_resi.' berhasil dibuat.');

        } catch (\Exception $e) {
            \Log::error('QR generation failed: '.$e->getMessage(), [
                'pengiriman_id' => $pengiriman->id,
                'no_resi' => $pengiriman->no_resi,
            ]);

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal membuat QR Code: '.$e->getMessage(),
                ], 500);
            }

            return back()->withErrors(['error' => 'Gagal membuat QR Code: '.$e->getMessage()]);
        }
    }

    /**
     * Display QR Code image (for inline viewing)
     */
    public function display(Pengiriman $pengiriman)
    {
        // Check if QR exists in database
        if (! $pengiriman->qr_code_path || ! $pengiriman->qr_code_data) {
            // Return error if QR doesn't exist
            return response()->view('errors.qr-not-found', [], 404);
        }

        // Check if file exists on filesystem
        if (! Storage::exists($pengiriman->qr_code_path)) {
            // Return a simple error QR if file doesn't exist
            return response()->view('errors.qr-not-found', [], 404);
        }

        $mimeType = str_ends_with(strtolower($pengiriman->qr_code_path), '.svg') ? 'image/svg+xml' : 'image/png';

        return response()->file(Storage::path($pengiriman->qr_code_path), [
            'Content-Type' => $mimeType,
        ]);
    }

    /**
     * Download QR Code image
     */
    public function download(Pengiriman $pengiriman)
    {
        // Check if QR exists in database
        if (! $pengiriman->qr_code_path || ! $pengiriman->qr_code_data) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code belum dibuat untuk pengiriman ini',
            ], 404);
        }

        // Check if file exists on filesystem
        if (! Storage::exists($pengiriman->qr_code_path)) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code file not found',
            ], 404);
        }

        // Generate filename for download
        $extension = pathinfo($pengiriman->qr_code_path, PATHINFO_EXTENSION) ?: 'png';
        $filename = "QR-{$pengiriman->no_resi}.{$extension}";

        // Return file download response
        return Storage::download($pengiriman->qr_code_path, $filename);
    }

    /**
     * Bulk generate QR codes - ENHANCED VERSION
     */
    public function bulkGenerate(Request $request)
    {
        try {
            $request->validate([
                'pengiriman_ids' => 'required|array',
                'pengiriman_ids.*' => 'exists:pengiriman,id',
            ]);

            $pengirimanList = Pengiriman::with(['wakafItem', 'jenisQuran', 'donatur'])
                ->whereIn('id', $request->pengiriman_ids)
                ->get();

            $results = [];
            $successCount = 0;
            $errorCount = 0;
            $skippedCount = 0;

            \Log::info('Bulk QR generation started', [
                'total_requested' => count($request->pengiriman_ids),
                'pengiriman_found' => $pengirimanList->count(),
                'user_id' => auth()->id(),
            ]);

            foreach ($pengirimanList as $pengiriman) {
                try {
                    // Check if QR Code already exists - skip if exists
                    if ($pengiriman->qr_code_path && Storage::exists($pengiriman->qr_code_path)) {
                        $results[] = [
                            'id' => $pengiriman->id,
                            'no_resi' => $pengiriman->no_resi,
                            'status' => 'skipped',
                            'qr_url' => Storage::url($pengiriman->qr_code_path),
                            'message' => 'QR Code sudah ada',
                        ];
                        $skippedCount++;

                        continue;
                    }

                    // Generate comprehensive QR data
                    $qrData = $this->buildQRData($pengiriman);

                    // Generate QR Code image
                    $qrCodePath = $this->generateQRImage($pengiriman, $qrData);

                    // Update pengiriman record
                    $pengiriman->update([
                        'qr_code_path' => $qrCodePath,
                        'qr_code_data' => $qrData, // Store as plain string
                    ]);

                    $results[] = [
                        'id' => $pengiriman->id,
                        'no_resi' => $pengiriman->no_resi,
                        'status' => 'success',
                        'qr_url' => Storage::url($qrCodePath),
                        'qr_data' => $qrData,
                        'message' => 'QR Code generated successfully',
                    ];
                    $successCount++;

                    \Log::info('QR generated successfully', [
                        'pengiriman_id' => $pengiriman->id,
                        'no_resi' => $pengiriman->no_resi,
                        'qr_path' => $qrCodePath,
                    ]);

                } catch (\Exception $e) {
                    \Log::error('QR generation failed for pengiriman', [
                        'pengiriman_id' => $pengiriman->id,
                        'no_resi' => $pengiriman->no_resi,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $results[] = [
                        'id' => $pengiriman->id,
                        'no_resi' => $pengiriman->no_resi,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                    $errorCount++;
                }
            }

            $totalProcessed = $successCount + $errorCount + $skippedCount;

            \Log::info('Bulk QR generation completed', [
                'total_processed' => $totalProcessed,
                'success' => $successCount,
                'errors' => $errorCount,
                'skipped' => $skippedCount,
                'user_id' => auth()->id(),
            ]);

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

        } catch (ValidationException $e) {
            \Log::warning('Bulk QR generation validation failed', [
                'errors' => $e->errors(),
                'request' => $request->all(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            \Log::error('Bulk QR generation failed: '.$e->getMessage(), [
                'request' => $request->all(),
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk QR generation failed: '.$e->getMessage(),
                'debug' => app()->environment('local') ? [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => basename($e->getFile()),
                ] : null,
            ], 500);
        }
    }

    /**
     * Build ultra-simple QR data - RESI ONLY LIKE OTHER EXPEDITIONS
     */
    private function buildQRData(Pengiriman $pengiriman)
    {
        // Ultra simple - just the resi number like most expeditions
        $qrData = $pengiriman->no_resi;

        \Log::info('Ultra-simple QR data built successfully', [
            'pengiriman_id' => $pengiriman->id,
            'no_resi' => $pengiriman->no_resi,
            'data_length' => strlen($qrData),
        ]);

        return $qrData;
    }

    /**
     * Generate QR Code image and save it to storage - ENHANCED VERSION
     */
    private function generateQRImage(Pengiriman $pengiriman, $qrData)
    {
        try {
            // Ensure storage directory exists
            if (! Storage::exists($this->storagePath)) {
                Storage::makeDirectory($this->storagePath);
            }

            // Generate image content with optimized settings for easy scanning
            $qrImage = QrCode::format('svg')
                ->size(300) // Smaller size since data is much simpler
                ->margin(1) // Minimal margin
                ->errorCorrection('L') // Low error correction for maximum simplicity
                ->generate($qrData); // Direct string, no JSON encoding

            // Define file path with timestamp for uniqueness
            $timestamp = now()->format('YmdHis');
            $filename = "QR-{$pengiriman->no_resi}-{$timestamp}.svg";
            $filePath = "{$this->storagePath}/{$filename}";

            // Save to storage
            Storage::put($filePath, $qrImage);

            // Verify file was created successfully
            if (! Storage::exists($filePath)) {
                throw new \Exception('QR file was not created successfully');
            }

            \Log::info('QR image generated successfully', [
                'pengiriman_id' => $pengiriman->id,
                'no_resi' => $pengiriman->no_resi,
                'file_path' => $filePath,
                'file_size' => Storage::size($filePath),
            ]);

            return $filePath;

        } catch (\Exception $e) {
            \Log::error('QR image generation failed', [
                'pengiriman_id' => $pengiriman->id,
                'no_resi' => $pengiriman->no_resi,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \Exception('Failed to generate QR image: '.$e->getMessage());
        }
    }

    /**
     * Generate security signature - ENHANCED VERSION
     */
    private function generateSignature(string $noResi, ?string $date = null)
    {
        $date = $date ?: date('Y-m-d');
        $appKey = config('app.key');

        if (! $appKey) {
            throw new \Exception('Application key not found for QR signature generation');
        }

        // Create more secure signature
        $signatureData = $noResi.'|'.$appKey.'|'.$date.'|ekspedisi_quran';

        return hash('sha256', $signatureData);
    }

    /**
     * Verify ultra-simple QR data (plain resi string)
     */
    public function verifyQRData($qrData)
    {
        // Handle both string and old JSON format for backward compatibility
        if (is_string($qrData)) {
            $resiNumber = trim($qrData);
        } elseif (is_array($qrData)) {
            $resiNumber = $qrData['resi'] ?? $qrData['no_resi'] ?? null;
        } else {
            return ['valid' => false, 'error' => 'Invalid QR data format'];
        }

        // Check if resi format is valid
        if (! preg_match('/^EQ-\d{4}-\d{5}$/', $resiNumber)) {
            return ['valid' => false, 'error' => 'Invalid resi format'];
        }

        // Verify that pengiriman exists
        $pengiriman = Pengiriman::where('no_resi', $resiNumber)->first();
        if (! $pengiriman) {
            return ['valid' => false, 'error' => 'Pengiriman not found'];
        }

        return ['valid' => true, 'data' => $resiNumber, 'pengiriman' => $pengiriman];
    }

    /**
     * API endpoint to verify QR data
     */
    public function verifyQRDataAPI(Request $request)
    {
        $request->validate([
            'qr_data' => 'required|string',
        ]);

        try {
            // Try to decode as JSON first (backward compatibility)
            $qrDataArray = json_decode($request->qr_data, true);

            // If JSON decode fails, treat as plain string
            $qrData = $qrDataArray ?: trim($request->qr_data);

            $verification = $this->verifyQRData($qrData);

            return response()->json([
                'success' => $verification['valid'],
                'message' => $verification['valid'] ? 'QR code is valid' : $verification['error'],
                'data' => $verification['valid'] ? $verification['data'] : null,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error verifying QR: '.$e->getMessage(),
            ], 500);
        }
    }
}
