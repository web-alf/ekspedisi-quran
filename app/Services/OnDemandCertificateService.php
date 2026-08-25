<?php

namespace App\Services;

use App\Models\WakafBatch;
use App\Models\Sertifikat;
use App\Models\CertificateTemplate;
use App\Helpers\HijriHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class OnDemandCertificateService
{
    private $templatePath = 'certificates.template';

    /**
     * Generate certificate on-demand without saving to storage
     */
    public function generateOnDemand(WakafBatch $wakafBatch, array $options = [])
    {
        try {
            // Eager load necessary relationships
            $wakafBatch->load(['donatur', 'sertifikat']);

            // Validate data
            $this->validateWakafBatchData($wakafBatch);

            // Get or create certificate record (without file_path)
            $sertifikat = $this->getOrCreateSertifikatRecord($wakafBatch, $options);

            // Get template
            $template = $this->getTemplate($sertifikat->template_id);

            // Prepare data for the certificate view
            $certificateData = $this->prepareCertificateData($wakafBatch);

            // Generate PDF and stream directly
            return $this->streamPdf($template, $certificateData, $wakafBatch->batch_code);

        } catch (Exception $e) {
            Log::error('On-demand certificate generation failed', [
                'wakaf_batch_id' => $wakafBatch->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Generate certificate by unique token
     */
    public function generateByToken(string $token)
    {
        // Decode token to get sertifikat ID
        $sertifikatId = $this->decodeToken($token);

        $sertifikat = Sertifikat::with(['wakafBatch.donatur', 'donatur', 'template'])
            ->findOrFail($sertifikatId);

        // Check if this is a consolidated certificate (direct donatur relationship)
        if (!$sertifikat->wakafBatch && $sertifikat->donatur) {
            // Use ConsolidatedCertificateService for consolidated certificates
            $consolidatedService = app(\App\Services\ConsolidatedCertificateService::class);
            return $consolidatedService->generateByToken($token);
        }

        // For regular WakafBatch-based certificates
        if (!$sertifikat->wakafBatch) {
            throw new Exception('Data batch wakaf tidak ditemukan.');
        }

        // ✅ FIX: Use same generation method as admin to support multi-page PDFs
        // Use BatchCertificateService to generate multi-wakif certificates
        $batchCertificateService = app(\App\Services\BatchCertificateService::class);

        // Get wakif items for this batch
        $wakifItems = $batchCertificateService->getWakifItemsForBatch($sertifikat->wakafBatch);
        $groupedWakifs = $wakifItems->groupBy('wakif_name');

        // Get template
        $template = $batchCertificateService->getTemplate();

        // Generate PDF content using the same method as admin download/preview
        // This returns raw PDF binary string, not a PDF object
        $pdfContent = $batchCertificateService->generateConsolidatedPdf(
            $sertifikat->wakafBatch,
            $groupedWakifs,
            $template
        );

        // Store the PDF content for use in download/preview
        // Create a wrapper object that mimics PDF API
        return new class($pdfContent) {
            private $content;

            public function __construct($content)
            {
                $this->content = $content;
            }

            public function download($filename)
            {
                return response($this->content, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                ]);
            }

            public function stream($filename)
            {
                return response($this->content, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "inline; filename=\"{$filename}\"",
                ]);
            }
        };
    }

    /**
     * Generate unique download token for a certificate
     */
    public function generateDownloadToken(Sertifikat $sertifikat): string
    {
        // Create a secure token that expires in 24 hours
        $payload = [
            'id' => $sertifikat->id,
            'exp' => now()->addHours(24)->timestamp,
            'hash' => md5($sertifikat->id . $sertifikat->nomor_sertifikat . config('app.key'))
        ];

        return base64_encode(json_encode($payload));
    }

    /**
     * Decode and validate token
     */
    private function decodeToken(string $token): int
    {
        try {
            $payload = json_decode(base64_decode($token), true);
            
            if (!$payload || !isset($payload['id']) || !isset($payload['exp']) || !isset($payload['hash'])) {
                throw new Exception('Invalid token format');
            }

            // Check expiration
            if ($payload['exp'] < now()->timestamp) {
                throw new Exception('Token has expired');
            }

            // Validate hash
            $sertifikat = Sertifikat::find($payload['id']);
            if (!$sertifikat) {
                throw new Exception('Certificate not found');
            }

            $expectedHash = md5($sertifikat->id . $sertifikat->nomor_sertifikat . config('app.key'));
            if ($payload['hash'] !== $expectedHash) {
                throw new Exception('Invalid token signature');
            }

            return $payload['id'];

        } catch (Exception $e) {
            throw new Exception('Invalid or expired token: ' . $e->getMessage());
        }
    }

    /**
     * Create certificate record without file storage (public method)
     */
    public function createCertificateRecord(WakafBatch $wakafBatch, array $options = [])
    {
        return $this->getOrCreateSertifikatRecord($wakafBatch, $options);
    }

    /**
     * Get public download URL for a certificate
     */
    public function getPublicDownloadUrl(Sertifikat $sertifikat): string
    {
        $token = $this->generateDownloadToken($sertifikat);
        return route('public.certificate.download', ['token' => $token]);
    }

    /**
     * Get certificate data by token (for filename generation)
     */
    public function getCertificateDataByToken(string $token): array
    {
        // Decode token to get sertifikat ID
        $sertifikatId = $this->decodeToken($token);
        
        $sertifikat = Sertifikat::with(['wakafBatch.donatur', 'donatur'])->findOrFail($sertifikatId);

        // Handle consolidated certificates (direct donatur relationship)
        if (!$sertifikat->wakafBatch && $sertifikat->donatur) {
            return [
                'nomor_sertifikat' => $sertifikat->nomor_sertifikat,
                'donatur_name' => $sertifikat->donatur->nama_donatur,
                'batch_code' => 'Konsolidasi',
            ];
        }

        // Handle regular WakafBatch-based certificates
        if (!$sertifikat->wakafBatch) {
            throw new Exception('Data sertifikat tidak lengkap.');
        }

        return [
            'nomor_sertifikat' => $sertifikat->nomor_sertifikat,
            'donatur_name' => $sertifikat->wakafBatch->donatur->nama_donatur,
            'batch_code' => $sertifikat->wakafBatch->batch_code,
        ];
    }

    /**
     * Validate WakafBatch data before generating a certificate
     */
    private function validateWakafBatchData(WakafBatch $wakafBatch)
    {
        if (!$wakafBatch->donatur) {
            throw new Exception('Data donatur tidak ditemukan untuk batch ini.');
        }
        if (empty($wakafBatch->donatur->nama_donatur)) {
            throw new Exception('Nama donatur tidak lengkap.');
        }
        if (empty($wakafBatch->total_quran) || $wakafBatch->total_quran <= 0) {
            throw new Exception('Jumlah Al-Quran pada batch tidak valid.');
        }
    }

    /**
     * Get or create certificate record without file storage
     */
    private function getOrCreateSertifikatRecord(WakafBatch $wakafBatch, array $options = [])
    {
        // Check if certificate already exists
        if ($wakafBatch->sertifikat) {
            return $wakafBatch->sertifikat;
        }

        // Get default template if not specified
        $templateId = $options['template_id'] ?? null;
        if (!$templateId) {
            $defaultTemplate = CertificateTemplate::where('is_default', true)
                ->where('is_active', true)
                ->first();
            $templateId = $defaultTemplate ? $defaultTemplate->id : null;
        }

        // Create new certificate record without file_path
        return Sertifikat::create([
            'wakaf_batch_id' => $wakafBatch->id,
            'template_id' => $templateId,
            'template_used' => $templateId ? CertificateTemplate::find($templateId)->slug : 'default',
            'generated_by' => auth()->id() ?? 1,
            'generated_at' => now(),
            // No file_path - generated on demand
        ]);
    }

    /**
     * Get template for certificate
     */
    private function getTemplate($templateId = null)
    {
        if ($templateId) {
            $template = CertificateTemplate::find($templateId);
            if ($template && $template->is_active) {
                return $template;
            }
        }

        // Get default template
        $defaultTemplate = CertificateTemplate::where('is_default', true)
            ->where('is_active', true)
            ->first();

        if (!$defaultTemplate) {
            throw new Exception('Belum ada template sertifikat yang tersedia di sistem. Silakan buat template sertifikat terlebih dahulu melalui menu Template Sertifikat sebelum membuat sertifikat.');
        }

        return $defaultTemplate;
    }

    /**
     * Prepare data for the certificate Blade template
     */
    private function prepareCertificateData(WakafBatch $wakafBatch)
    {
        // Load the jenisQuran relationship if not already loaded
        if (!$wakafBatch->relationLoaded('jenisQuran')) {
            $wakafBatch->load('jenisQuran');
        }

        // Format specific Quran type text
        $mushafText = $wakafBatch->total_quran;
        if ($wakafBatch->jenisQuran) {
            switch ($wakafBatch->jenisQuran->kode_jenis) {
                case 'A5':
                    $mushafText .= ' Al-Qur\'an ukuran A5';
                    break;
                case 'A6':
                    $mushafText .= ' Al-Qur\'an ukuran A6';
                    break;
                case 'IQRO':
                    $mushafText .= ' Buku Iqra';
                    break;
                default:
                    $mushafText .= ' ' . $wakafBatch->jenisQuran->nama_jenis;
                    break;
            }
        } else {
            $mushafText .= ' Mushaf';
        }

        // Get wakif names from all pengiriman of this donatur
        $wakifNames = \App\Models\Pengiriman::where('donatur_id', $wakafBatch->donatur_id)
            ->with('wakafItem:id,pengiriman_id,wakif_name')
            ->get()
            ->map(function($p) {
                return $p->wakafItem ? $p->wakafItem->wakif_name : null;
            })
            ->filter()
            ->unique()
            ->values();

        // Join wakif names with comma and "dan" for the last item (Indonesian style)
        $wakifNamesString = '';
        if ($wakifNames->count() > 1) {
            $lastWakif = $wakifNames->pop();
            $wakifNamesString = $wakifNames->implode(', ') . ' dan ' . $lastWakif;
        } else {
            $wakifNamesString = $wakifNames->first() ?: $wakafBatch->donatur->nama_donatur;
        }

        // Simple certificate data - with Hijriyah date instead of mushaf count
        return [
            'wakif_name' => $wakifNamesString,
            'hijri_date' => HijriHelper::convertToHijri($wakafBatch->tanggal_wakaf),
            'batch_code' => $wakafBatch->batch_code,
            'tanggal_wakaf' => $wakafBatch->tanggal_wakaf->format('d F Y'),
        ];
    }

    /**
     * Generate and stream PDF without saving
     */
    private function streamPdf(CertificateTemplate $template, array $certificateData, string $batchCode)
    {
        // Generate base64 template image for the view
        $templateBase64 = $this->getTemplateBase64($template);
        
        $viewData = [
            'data' => $certificateData,
            'template' => $template,
            'field_positions' => $template->field_positions,
            'template_base64' => $templateBase64,
        ];

        $pdf = PDF::loadView($this->templatePath, $viewData)
            ->setPaper([0, 0, $template->width, $template->height], 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'dpi' => 96,
                'defaultFont' => 'Arial',
                'isFontSubsettingEnabled' => true,
                'isPhpEnabled' => true,
            ]);

        // Create standardized filename: Ekspedisi-Quran-{tanggal generate}-CERT-{nomor sertifikat}-{nama donatur}
        $generateDate = now()->format('Y-m-d');
        $donaturName = $this->sanitizeFilename($certificateData['wakif_name']);
        $filename = "Ekspedisi-Quran-{$generateDate}-CERT-{$batchCode}-{$donaturName}.pdf";

        // Log successful generation
        Log::info('Certificate generated on-demand', [
            'batch_code' => $batchCode,
            'template_id' => $template->id,
            'generated_at' => now()
        ]);

        return $pdf;
    }

    /**
     * Get base64 encoded template image
     */
    private function getTemplateBase64(CertificateTemplate $template)
    {
        try {
            // Get template file path
            $templatePath = storage_path('app/public/' . $template->template_file_path);
            
            // Check if file exists
            if (!file_exists($templatePath)) {
                throw new Exception("Template file not found: {$template->template_file_path}");
            }
            
            // Get file contents and encode to base64
            $imageData = file_get_contents($templatePath);
            $base64 = base64_encode($imageData);
            
            // Get file extension for proper MIME type
            $extension = pathinfo($templatePath, PATHINFO_EXTENSION);
            $mimeType = $this->getMimeType($extension);
            
            return "data:{$mimeType};base64,{$base64}";
            
        } catch (Exception $e) {
            Log::error('Failed to generate template base64', [
                'template_id' => $template->id,
                'template_file_path' => $template->template_file_path,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception("Failed to load template image: " . $e->getMessage());
        }
    }

    /**
     * Get MIME type for image extension
     */
    private function getMimeType($extension)
    {
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp'
        ];
        
        return $mimeTypes[strtolower($extension)] ?? 'image/jpeg';
    }

    /**
     * Sanitize filename for safe download
     */
    public function sanitizeFilename(string $filename): string
    {
        // Remove or replace problematic characters
        $filename = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $filename);
        
        // Replace multiple spaces with single dash
        $filename = preg_replace('/\s+/', '-', $filename);
        
        // Remove multiple consecutive dashes
        $filename = preg_replace('/-+/', '-', $filename);
        
        // Trim dashes from start and end
        $filename = trim($filename, '-');
        
        return $filename;
    }
}