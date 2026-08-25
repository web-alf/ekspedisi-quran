<?php

namespace App\Services;

use App\Helpers\HijriHelper;
use App\Models\CertificateTemplate;
use App\Models\Sertifikat;
use App\Models\WakafBatch;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CertificateService
{
    private $templatePath = 'certificates.template';

    private $storagePath = 'certificates';

    /**
     * Generate certificate untuk satu WakafBatch.
     */
    public function generateCertificateForBatch(WakafBatch $wakafBatch, array $options = [])
    {
        try {
            // Eager load necessary relationships
            $wakafBatch->load(['wakif', 'sertifikat']);

            // Check if certificate already exists and regeneration is not requested
            if ($wakafBatch->sertifikat && ! ($options['regenerate'] ?? false)) {
                throw new Exception('Sertifikat sudah ada untuk batch wakaf ini.');
            }

            // Validate data
            $this->validateWakafBatchData($wakafBatch);

            // Get template
            $template = $this->getTemplate($options['template_id'] ?? null);

            // Prepare data for the certificate view
            $certificateData = $this->prepareCertificateData($wakafBatch);

            // Generate PDF content from Blade template
            $pdfContent = $this->generatePdfFromTemplate($template, $certificateData);

            // Save PDF to storage
            $filePath = $this->saveToStorage($pdfContent, $wakafBatch->batch_code);

            // Create or update the certificate record in the database
            $sertifikat = $this->createOrUpdateSertifikatRecord($wakafBatch, $template, $filePath, $options);

            Log::info('Certificate for batch generated successfully', [
                'wakaf_batch_id' => $wakafBatch->id,
                'batch_code' => $wakafBatch->batch_code,
                'certificate_id' => $sertifikat->id,
                'file_path' => $filePath,
            ]);

            return $sertifikat;

        } catch (Exception $e) {
            Log::error('Certificate generation for batch failed', [
                'wakaf_batch_id' => $wakafBatch->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Validate WakafBatch data before generating a certificate.
     */
    private function validateWakafBatchData(WakafBatch $wakafBatch)
    {
        if (! $wakafBatch->wakif) {
            throw new Exception('Data wakif tidak ditemukan untuk batch ini.');
        }
        if (empty($wakafBatch->wakif->nama_wakif)) {
            throw new Exception('Nama wakif tidak lengkap.');
        }
        if (empty($wakafBatch->total_quran) || $wakafBatch->total_quran <= 0) {
            throw new Exception('Jumlah Al-Quran pada batch tidak valid.');
        }
    }

    /**
     * Prepare data for the certificate Blade template.
     */
    private function prepareCertificateData(WakafBatch $wakafBatch)
    {
        // Load the jenisQuran relationship if not already loaded
        if (! $wakafBatch->relationLoaded('jenisQuran')) {
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
                    $mushafText .= ' '.$wakafBatch->jenisQuran->nama_jenis;
                    break;
            }
        } else {
            $mushafText .= ' Mushaf';
        }

        // Convert to Hijri date
        $hijriDate = '';
        try {
            $hijriDate = HijriHelper::convertToHijri($wakafBatch->tanggal_wakaf);
        } catch (Exception $e) {
            Log::warning('Failed to convert Hijri date for certificate', [
                'wakaf_batch_id' => $wakafBatch->id,
                'tanggal_wakaf' => $wakafBatch->tanggal_wakaf,
                'error' => $e->getMessage(),
            ]);
            // Fallback to empty or default text
            $hijriDate = 'Tanggal Hijriah tidak tersedia';
        }

        return [
            'wakif_name' => $wakafBatch->wakif->nama_wakif,
            'mushaf_count' => $mushafText,
            'batch_code' => $wakafBatch->batch_code,
            'tanggal_wakaf' => $wakafBatch->tanggal_wakaf->format('d F Y'),
            'hijri_date' => 'Semarang, '.$hijriDate,
        ];
    }

    /**
     * Get the certificate template
     */
    private function getTemplate(?int $templateId = null)
    {
        if ($templateId) {
            $template = CertificateTemplate::find($templateId);
            if (! $template) {
                throw new Exception('Template sertifikat dengan ID '.$templateId.' tidak ditemukan. Silakan pilih template yang tersedia atau hubungi administrator.');
            }

            return $template;
        }

        $template = CertificateTemplate::default()->active()->first();
        if (! $template) {
            throw new Exception('Belum ada template sertifikat yang tersedia di sistem. Silakan buat template sertifikat terlebih dahulu melalui menu Template Sertifikat sebelum membuat sertifikat batch.');
        }

        return $template;
    }

    /**
     * Generate PDF from Blade template with optimized font rendering
     */
    private function generatePdfFromTemplate(CertificateTemplate $template, array $data)
    {
        if (! $template->templateFileExists()) {
            throw new Exception("File template tidak ditemukan: {$template->template_file_path}");
        }

        // Read image content and convert to Base64
        $imagePath = $template->getFullTemplatePath();
        $imageData = base64_encode(file_get_contents($imagePath));
        $imageMime = mime_content_type($imagePath);
        $imageBase64 = "data:{$imageMime};base64,{$imageData}";

        $viewData = [
            'template' => $template,
            'data' => $data,
            'template_base64' => $imageBase64,
            'field_positions' => $template->field_positions,
        ];

        $pdf = Pdf::loadView($this->templatePath, $viewData)
            ->setPaper([0, 0, $template->width, $template->height], 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'dpi' => 96, // Match browser DPI for consistency
                'defaultFont' => 'Times-Roman', // Force Times New Roman font
                'isFontSubsettingEnabled' => true,
                'isPhpEnabled' => true,
            ]);

        return $pdf->output();
    }

    /**
     * Save PDF to storage using batch code for filename.
     */
    private function saveToStorage(string $pdfContent, string $batchCode)
    {
        $year = date('Y');
        $month = date('m');

        $directory = "{$this->storagePath}/{$year}/{$month}";

        Storage::makeDirectory($directory);

        $filename = "cert-{$batchCode}-".date('YmdHis').'.pdf';
        $filePath = "{$directory}/{$filename}";

        Storage::put($filePath, $pdfContent);

        return $filePath;
    }

    /**
     * Create or update a certificate record for a WakafBatch.
     */
    private function createOrUpdateSertifikatRecord(WakafBatch $wakafBatch, CertificateTemplate $template, string $filePath, array $options = [])
    {
        // If regenerating, delete the old file before updating the record
        if (($options['regenerate'] ?? false) && $wakafBatch->sertifikat) {
            if (Storage::exists($wakafBatch->sertifikat->file_path)) {
                Storage::delete($wakafBatch->sertifikat->file_path);
            }
        }

        return Sertifikat::updateOrCreate(
            ['wakaf_batch_id' => $wakafBatch->id],
            [
                'template_used' => $template->slug ?? 'default',
                'template_id' => $template->id ?? null,
                'file_path' => $filePath,
                'generated_by' => auth()->id() ?? 1,
                'generated_at' => now(),
            ]
        );
    }

    /**
     * Get certificate file content
     */
    public function getCertificateFile(Sertifikat $sertifikat)
    {
        if (! $sertifikat->fileExists()) {
            throw new Exception('File sertifikat tidak ditemukan');
        }

        try {
            $content = Storage::get($sertifikat->getStoragePath());
            if ($content === false) {
                throw new Exception('Gagal membaca file sertifikat');
            }

            return $content;
        } catch (Exception $e) {
            Log::error('Failed to read certificate file', [
                'sertifikat_id' => $sertifikat->id,
                'file_path' => $sertifikat->file_path,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Gagal membaca file sertifikat: '.$e->getMessage());
        }
    }

    /**
     * Get certificate download response.
     */
    public function downloadCertificate(Sertifikat $sertifikat)
    {
        if (! $sertifikat->fileExists()) {
            throw new Exception('File sertifikat tidak ditemukan di storage.');
        }

        try {
            $sertifikat->load('wakafBatch');
            $batchCode = $sertifikat->wakafBatch->batch_code ?? 'UNKNOWN';
            $filename = "Sertifikat-Wakaf-{$batchCode}.pdf";

            $fullPath = $sertifikat->getFullFilePath();

            // Verify file is readable
            if (! is_readable($fullPath)) {
                throw new Exception('File sertifikat tidak dapat dibaca');
            }

            return response()->download(
                $fullPath,
                $filename,
                ['Content-Type' => 'application/pdf']
            );
        } catch (Exception $e) {
            Log::error('Failed to download certificate', [
                'sertifikat_id' => $sertifikat->id,
                'file_path' => $sertifikat->file_path,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Gagal mengunduh sertifikat: '.$e->getMessage());
        }
    }

    /**
     * Get certificates statistics based on the new batch system.
     */
    public function getStatistics()
    {
        return [
            'total_generated' => Sertifikat::count(),
            'generated_today' => Sertifikat::generatedToday()->count(),
            'generated_this_month' => Sertifikat::generatedThisMonth()->count(),
            'pending_generation' => WakafBatch::where('status', 'completed')
                ->whereDoesntHave('sertifikat')
                ->count(),
            'not_sent' => Sertifikat::notSent()->count(),
        ];
    }

    /**
     * Auto-generate certificates for all completed batches that don't have one.
     */
    public function autoGenerateForCompletedBatches()
    {
        $completedBatches = WakafBatch::where('status', 'completed')
            ->whereDoesntHave('sertifikat')
            ->get();

        $results = [];

        foreach ($completedBatches as $batch) {
            try {
                $sertifikat = $this->generateCertificateForBatch($batch);
                $results[] = [
                    'success' => true,
                    'batch_code' => $batch->batch_code,
                    'certificate_number' => $sertifikat->nomor_sertifikat,
                ];
            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'batch_code' => $batch->batch_code,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
