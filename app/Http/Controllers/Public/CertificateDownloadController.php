<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\OnDemandCertificateService;
use Illuminate\Http\Request;
use Exception;

class CertificateDownloadController extends Controller
{
    protected $certificateService;

    public function __construct(OnDemandCertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
    }

    /**
     * Download certificate using secure token
     */
    public function download(Request $request, string $token)
    {
        try {
            // Decode token to get certificate info
            $certificateData = $this->certificateService->getCertificateDataByToken($token);
            
            // Generate PDF on-demand
            $pdf = $this->certificateService->generateByToken($token);
            
            // Create standardized filename: Ekspedisi-Quran-{tanggal generate}-CERT-{nomor sertifikat}-{nama donatur}
            $donaturName = $this->certificateService->sanitizeFilename($certificateData['donatur_name']);
            $generateDate = now()->format('Y-m-d');
            $filename = "Ekspedisi-Quran-{$generateDate}-{$certificateData['nomor_sertifikat']}-{$donaturName}.pdf";
            
            return $pdf->download($filename);
            
        } catch (Exception $e) {
            // Log error for monitoring
            \Log::error('Certificate download failed', [
                'token' => substr($token, 0, 20) . '...', // Log partial token for security
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Return user-friendly error page
            return response()->view('errors.certificate-not-found', [
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Preview certificate in browser (optional)
     */
    public function preview(Request $request, string $token)
    {
        try {
            // Decode token to get certificate info
            $certificateData = $this->certificateService->getCertificateDataByToken($token);
            
            // Generate PDF on-demand
            $pdf = $this->certificateService->generateByToken($token);
            
            // Create standardized filename for preview
            $donaturName = $this->certificateService->sanitizeFilename($certificateData['donatur_name']);
            $generateDate = now()->format('Y-m-d');
            $filename = "Preview-Ekspedisi-Quran-{$generateDate}-{$certificateData['nomor_sertifikat']}-{$donaturName}.pdf";
            
            return $pdf->stream($filename);
                
        } catch (Exception $e) {
            return response()->view('errors.certificate-not-found', [
                'message' => $e->getMessage()
            ], 404);
        }
    }
}