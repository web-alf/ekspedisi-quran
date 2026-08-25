<?php

namespace App\Services;

use App\Helpers\HijriHelper;
use App\Models\CertificateTemplate;
use App\Models\Donatur;
use App\Models\Sertifikat;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Log;

class ConsolidatedCertificateService
{
    private $templatePath = 'certificates.consolidated-template';

    /**
     * Generate consolidated certificate for a donor with all their wakaf types
     */
    public function generateConsolidatedCertificate(Donatur $donatur, array $options = [])
    {
        try {
            // Validate donor data
            $this->validateDonaturData($donatur);

            // Get or create consolidated certificate record
            $sertifikat = $this->getOrCreateConsolidatedSertifikat($donatur, $options);

            // Get template
            $template = $this->getTemplate($sertifikat->template_id);

            // Prepare consolidated data for the certificate view
            $certificateData = $this->prepareConsolidatedCertificateData($donatur);

            // Generate PDF and stream directly
            return $this->streamPdf($template, $certificateData, $donatur->kode_donatur);

        } catch (Exception $e) {
            Log::error('Consolidated certificate generation failed', [
                'donatur_id' => $donatur->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate consolidated certificate with specific certificate number for filename
     */
    public function generateConsolidatedCertificateWithNumber(Donatur $donatur, string $certificateNumber, array $options = [])
    {
        try {
            // Validate donor data
            $this->validateDonaturData($donatur);

            // Get template
            $template = $this->getTemplate($options['template_id'] ?? null);

            // Prepare consolidated data for the certificate view
            $certificateData = $this->prepareConsolidatedCertificateData($donatur);

            // Generate PDF and stream directly with certificate number
            return $this->streamPdfWithCertNumber($template, $certificateData, $donatur->kode_donatur, $certificateNumber, $donatur->nama_donatur);

        } catch (Exception $e) {
            Log::error('Consolidated certificate generation failed', [
                'donatur_id' => $donatur->id,
                'certificate_number' => $certificateNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate certificate by token
     */
    public function generateByToken(string $token)
    {
        // Decode token to get sertifikat ID
        $sertifikatId = $this->decodeToken($token);

        $sertifikat = Sertifikat::with(['donatur'])
            ->findOrFail($sertifikatId);

        if (! $sertifikat->donatur) {
            throw new Exception('Data donatur tidak ditemukan.');
        }

        return $this->generateConsolidatedCertificateWithNumber($sertifikat->donatur, $sertifikat->nomor_sertifikat);
    }

    /**
     * Generate unique download token for a certificate
     */
    public function generateDownloadToken(Sertifikat $sertifikat): string
    {
        $payload = [
            'id' => $sertifikat->id,
            'exp' => now()->addHours(24)->timestamp,
            'hash' => md5($sertifikat->id.$sertifikat->nomor_sertifikat.config('app.key')),
        ];

        return base64_encode(json_encode($payload));
    }

    /**
     * Get public download URL for a certificate
     */
    public function getPublicDownloadUrl(Sertifikat $sertifikat): string
    {
        $token = $this->generateDownloadToken($sertifikat);

        return route('public.certificate.consolidated-download', ['token' => $token]);
    }

    /**
     * Create consolidated certificate record
     */
    public function createConsolidatedCertificateRecord(Donatur $donatur, array $options = [])
    {
        return $this->getOrCreateConsolidatedSertifikat($donatur, $options);
    }

    /**
     * Validate Donatur data before generating a certificate
     */
    private function validateDonaturData(Donatur $donatur)
    {
        if (empty($donatur->nama_donatur)) {
            throw new Exception('Nama donatur tidak lengkap.');
        }

        $totalQuran = $donatur->total_a5_count + $donatur->total_a6_count + $donatur->total_iqra_count;
        if ($totalQuran <= 0) {
            throw new Exception('Donatur tidak memiliki wakaf Al-Quran.');
        }
    }

    /**
     * Get or create consolidated certificate record for donatur
     */
    private function getOrCreateConsolidatedSertifikat(Donatur $donatur, array $options = [])
    {
        // Check if consolidated certificate already exists for this donatur
        $existingSertifikat = Sertifikat::where('donatur_id', $donatur->id)
            ->where('is_consolidated', true)
            ->first();

        if ($existingSertifikat) {
            return $existingSertifikat;
        }

        // Get default template if not specified
        $templateId = $options['template_id'] ?? null;
        if (! $templateId) {
            $defaultTemplate = CertificateTemplate::where('is_default', true)
                ->where('is_active', true)
                ->first();
            $templateId = $defaultTemplate ? $defaultTemplate->id : null;
        }

        // Create new consolidated certificate record
        return Sertifikat::create([
            'donatur_id' => $donatur->id,
            'wakaf_batch_id' => null, // No specific batch - this covers all
            'template_id' => $templateId,
            'template_used' => $templateId ? CertificateTemplate::find($templateId)->slug : 'default',
            'is_consolidated' => true,
            'generated_by' => auth()->id() ?? 1,
            'generated_at' => now(),
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

        if (! $defaultTemplate) {
            throw new Exception('Belum ada template sertifikat yang tersedia di sistem. Silakan buat template sertifikat terlebih dahulu melalui menu Template Sertifikat sebelum membuat sertifikat.');
        }

        return $defaultTemplate;
    }

    /**
     * Prepare consolidated data for the certificate Blade template
     * ✅ MULTI-PAGE FIX: Returns array of pages if multiple wakifs exist, otherwise single page data
     */
    private function prepareConsolidatedCertificateData(Donatur $donatur)
    {
        // Load wakaf items with wakif names
        $donatur->load(['wakafItems' => function ($q) {
            $q->whereNotNull('wakif_name')
                ->where('wakif_name', '!=', '')
                ->orderBy('global_sequence');
        }]);

        $wakafItems = $donatur->wakafItems;

        // If there are wakaf items with wakif names, prepare multi-page data
        if ($wakafItems->isNotEmpty()) {
            $groupedWakifs = $wakafItems->groupBy('wakif_name');
            $allPages = [];

            foreach ($groupedWakifs as $wakifName => $items) {
                // Prepare data for each wakif
                $certificateData = [
                    'wakif_name' => $wakifName,
                    'hijri_date' => 'Semarang, '.HijriHelper::convertToHijri($donatur->donation_date ?? now()),
                ];

                $allPages[] = $certificateData;
            }

            // Return array of pages for multi-page PDF
            return [
                'is_multi_page' => true,
                'pages' => $allPages,
            ];
        }

        // Fallback: single page with donatur name (backward compatibility)
        $wakafDetails = [];
        $totalMushaf = 0;

        // Collect all wakaf types and quantities
        if ($donatur->total_a5_count > 0) {
            $wakafDetails[] = $donatur->total_a5_count.' Al-Qur\'an ukuran A5';
            $totalMushaf += $donatur->total_a5_count;
        }

        if ($donatur->total_a6_count > 0) {
            $wakafDetails[] = $donatur->total_a6_count.' Al-Qur\'an ukuran A6';
            $totalMushaf += $donatur->total_a6_count;
        }

        if ($donatur->total_iqra_count > 0) {
            $wakafDetails[] = $donatur->total_iqra_count.' Buku Iqra';
            $totalMushaf += $donatur->total_iqra_count;
        }

        // Format the details
        $wakafDetailText = '';
        if (count($wakafDetails) > 1) {
            $wakafDetailText = implode(', ', array_slice($wakafDetails, 0, -1)).' dan '.end($wakafDetails);
        } else {
            $wakafDetailText = $wakafDetails[0] ?? '';
        }

        return [
            'is_multi_page' => false,
            'wakif_name' => $donatur->nama_donatur,
            'hijri_date' => 'Semarang, '.HijriHelper::convertToHijri($donatur->donation_date ?? now()),
            'wakaf_details' => $wakafDetailText,
            'donatur_code' => $donatur->kode_donatur,
            'tanggal_wakaf' => $donatur->donation_date ? $donatur->donation_date->format('d F Y') : now()->format('d F Y'),
        ];
    }

    /**
     * Generate and stream PDF without saving
     * ✅ MULTI-PAGE FIX: Detects multi-page data and uses multi-template view
     */
    private function streamPdf(CertificateTemplate $template, array $certificateData, string $donaturCode)
    {
        // Generate base64 template image for the view
        $templateBase64 = $this->getTemplateBase64($template);

        // ✅ MULTI-PAGE FIX: Check if this is multi-page data
        if (isset($certificateData['is_multi_page']) && $certificateData['is_multi_page'] === true) {
            // Use multi-page template
            $viewData = [
                'template' => $template,
                'pages' => $certificateData['pages'],
                'template_base64' => $templateBase64,
                'field_positions' => $template->field_positions,
            ];

            $pdf = PDF::loadView('certificates.multi-template', $viewData)
                ->setPaper([0, 0, $template->width, $template->height], 'landscape')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => false,
                    'dpi' => 96,
                    'defaultFont' => 'Times-Roman',
                    'isFontSubsettingEnabled' => true,
                    'isPhpEnabled' => true,
                ]);

            // Log successful multi-page generation
            Log::info('Multi-page consolidated certificate generated on-demand', [
                'donatur_code' => $donaturCode,
                'template_id' => $template->id,
                'total_pages' => count($certificateData['pages']),
                'generated_at' => now(),
            ]);

            return $pdf;
        }

        // Single page certificate (backward compatibility)
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

        // Log successful generation
        Log::info('Consolidated certificate generated on-demand', [
            'donatur_code' => $donaturCode,
            'template_id' => $template->id,
            'generated_at' => now(),
        ]);

        return $pdf;
    }

    /**
     * Generate and stream PDF with certificate number for standardized filename
     * ✅ MULTI-PAGE FIX: Detects multi-page data and uses multi-template view
     */
    private function streamPdfWithCertNumber(CertificateTemplate $template, array $certificateData, string $donaturCode, string $certificateNumber, string $donaturName)
    {
        // Generate base64 template image for the view
        $templateBase64 = $this->getTemplateBase64($template);

        // ✅ MULTI-PAGE FIX: Check if this is multi-page data
        if (isset($certificateData['is_multi_page']) && $certificateData['is_multi_page'] === true) {
            // Use multi-page template
            $viewData = [
                'template' => $template,
                'pages' => $certificateData['pages'],
                'template_base64' => $templateBase64,
                'field_positions' => $template->field_positions,
            ];

            $pdf = PDF::loadView('certificates.multi-template', $viewData)
                ->setPaper([0, 0, $template->width, $template->height], 'landscape')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => false,
                    'dpi' => 96,
                    'defaultFont' => 'Times-Roman',
                    'isFontSubsettingEnabled' => true,
                    'isPhpEnabled' => true,
                ]);

            // Log successful multi-page generation
            Log::info('Multi-page consolidated certificate generated on-demand', [
                'donatur_code' => $donaturCode,
                'certificate_number' => $certificateNumber,
                'template_id' => $template->id,
                'total_pages' => count($certificateData['pages']),
                'generated_at' => now(),
            ]);

            return $pdf;
        }

        // Single page certificate (backward compatibility)
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

        // Log successful generation
        Log::info('Consolidated certificate generated on-demand', [
            'donatur_code' => $donaturCode,
            'certificate_number' => $certificateNumber,
            'template_id' => $template->id,
            'generated_at' => now(),
        ]);

        return $pdf;
    }

    /**
     * Get base64 encoded template image
     */
    private function getTemplateBase64(CertificateTemplate $template)
    {
        try {
            $templatePath = storage_path('app/public/'.$template->template_file_path);

            if (! file_exists($templatePath)) {
                throw new Exception("Template file not found: {$template->template_file_path}");
            }

            $imageData = file_get_contents($templatePath);
            $base64 = base64_encode($imageData);

            $extension = pathinfo($templatePath, PATHINFO_EXTENSION);
            $mimeType = $this->getMimeType($extension);

            return "data:{$mimeType};base64,{$base64}";

        } catch (Exception $e) {
            Log::error('Failed to generate template base64', [
                'template_id' => $template->id,
                'template_file_path' => $template->template_file_path,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to load template image: '.$e->getMessage());
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
            'webp' => 'image/webp',
        ];

        return $mimeTypes[strtolower($extension)] ?? 'image/jpeg';
    }

    /**
     * Decode and validate token
     */
    private function decodeToken(string $token): int
    {
        try {
            $payload = json_decode(base64_decode($token), true);

            if (! $payload || ! isset($payload['id']) || ! isset($payload['exp']) || ! isset($payload['hash'])) {
                throw new Exception('Invalid token format');
            }

            if ($payload['exp'] < now()->timestamp) {
                throw new Exception('Token has expired');
            }

            $sertifikat = Sertifikat::find($payload['id']);
            if (! $sertifikat) {
                throw new Exception('Certificate not found');
            }

            $expectedHash = md5($sertifikat->id.$sertifikat->nomor_sertifikat.config('app.key'));
            if ($payload['hash'] !== $expectedHash) {
                throw new Exception('Invalid token signature');
            }

            return $payload['id'];

        } catch (Exception $e) {
            throw new Exception('Invalid or expired token: '.$e->getMessage());
        }
    }

    /**
     * Sanitize filename for safe download
     */
    public function sanitizeFilename(string $filename): string
    {
        $filename = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $filename);
        $filename = preg_replace('/\s+/', '-', $filename);
        $filename = preg_replace('/-+/', '-', $filename);
        $filename = trim($filename, '-');

        return $filename;
    }
}
