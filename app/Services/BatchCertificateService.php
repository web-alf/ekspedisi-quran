<?php

namespace App\Services;

use App\Helpers\HijriHelper;
use App\Models\CertificateTemplate;
use App\Models\Sertifikat;
use App\Models\WakafBatch;
use App\Models\WakafItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Log;

class BatchCertificateService
{
    private $templatePath = 'certificates.template';

    /**
     * Generate certificate untuk satu WakafBatch berdasarkan wakif items
     */
    public function generateBatchCertificate(WakafBatch $wakafBatch, array $options = [])
    {
        try {
            // Load relationships
            $wakafBatch->load(['donatur', 'jenisQuran', 'sertifikat']);

            // Check if certificate already exists
            if ($wakafBatch->sertifikat && ! ($options['regenerate'] ?? false)) {
                throw new Exception('Sertifikat sudah ada untuk batch wakaf ini.');
            }

            // Get all wakif items for this batch - using direct query for consolidated batches
            // For consolidated batches, we want ALL wakif items for the donatur
            $wakifItems = WakafItem::where('donatur_id', $wakafBatch->donatur_id)
                ->whereNotNull('wakif_name')
                ->where('wakif_name', '!=', '')
                ->orderBy('global_sequence')
                ->get();

            if ($wakifItems->isEmpty()) {
                // If no wakif items, create default using donatur name
                $wakifItems = collect([
                    (object) [
                        'wakif_name' => $wakafBatch->donatur->nama_donatur,
                        'wakaf_type' => 'Mixed', // Default type
                        'count' => $wakafBatch->total_quran,
                    ],
                ]);
            }

            // Group wakif items by name
            $groupedWakifs = $wakifItems->groupBy('wakif_name');

            // Get template
            $template = $this->getTemplate($options['template_id'] ?? null);

            // Generate consolidated PDF for validation (content not stored)
            $this->generateConsolidatedPdf($wakafBatch, $groupedWakifs, $template);

            // Create or update certificate record (no file storage - on-demand generation)
            $sertifikat = $this->createOrUpdateSertifikatRecord($wakafBatch, $template, $options);

            Log::info('Batch certificate generated successfully', [
                'wakaf_batch_id' => $wakafBatch->id,
                'batch_code' => $wakafBatch->batch_code,
                'wakif_count' => $groupedWakifs->count(),
                'certificate_id' => $sertifikat->id,
            ]);

            return $sertifikat;

        } catch (Exception $e) {
            Log::error('Batch certificate generation failed', [
                'wakaf_batch_id' => $wakafBatch->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get wakif items related to this batch
     * CONSOLIDATED VERSION: Always return ALL wakif items for the donatur
     */
    public function getWakifItemsForBatch(WakafBatch $wakafBatch)
    {
        // CONSOLIDATED LOGIC: Return ALL wakif items for this donatur
        // This ensures consolidated certificates include all wakif names in one batch
        return WakafItem::where('donatur_id', $wakafBatch->donatur_id)
            ->whereNotNull('wakif_name')
            ->where('wakif_name', '!=', '')
            ->orderBy('global_sequence')
            ->get();
    }

    /**
     * Generate consolidated PDF with multiple certificates
     */
    public function generateConsolidatedPdf(WakafBatch $wakafBatch, $groupedWakifs, CertificateTemplate $template)
    {
        $allPages = [];

        foreach ($groupedWakifs as $wakifName => $items) {
            // Calculate total mushaf for this wakif
            $totalMushaf = $items->count();

            // Prepare data for each wakif - only send fields that are supported by template
            $certificateData = [
                'wakif_name' => $wakifName,
                'hijri_date' => 'Semarang, '.HijriHelper::convertToHijri($wakafBatch->tanggal_wakaf),
            ];

            $allPages[] = $certificateData;
        }

        // Generate PDF with all pages
        return $this->generateMultiPagePdf($template, $allPages);
    }

    /**
     * Format mushaf text based on types
     */
    private function formatMushafText($total, $items)
    {
        // If single item with count property (fallback case)
        if ($items->count() == 1 && isset($items->first()->count)) {
            return $items->first()->count.' Mushaf Al-Qur\'an';
        }

        $types = $items->groupBy('wakaf_type')->map->count();
        $parts = [];

        foreach ($types as $type => $count) {
            switch ($type) {
                case 'A5':
                    $parts[] = $count.' Al-Qur\'an ukuran A5';
                    break;
                case 'A6':
                    $parts[] = $count.' Al-Qur\'an ukuran A6';
                    break;
                case 'IQRA':
                case 'IQRO':
                    $parts[] = $count.' Buku Iqra';
                    break;
                case 'Mixed':
                    $parts[] = $count.' Mushaf Al-Qur\'an';
                    break;
                default:
                    $parts[] = $count.' Mushaf';
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Get donation number (keberapa kali donasi)
     */
    private function getDonationNumber(WakafBatch $wakafBatch)
    {
        // Count previous batches for this donatur
        $previousBatches = WakafBatch::where('donatur_id', $wakafBatch->donatur_id)
            ->where('created_at', '<', $wakafBatch->created_at)
            ->count();

        $number = $previousBatches + 1;

        // Format: Donasi Pertama, Kedua, dst
        $ordinals = [
            1 => 'Pertama',
            2 => 'Kedua',
            3 => 'Ketiga',
            4 => 'Keempat',
            5 => 'Kelima',
            6 => 'Keenam',
            7 => 'Ketujuh',
            8 => 'Kedelapan',
            9 => 'Kesembilan',
            10 => 'Kesepuluh',
        ];

        return isset($ordinals[$number]) ? "Donasi {$ordinals[$number]}" : "Donasi ke-{$number}";
    }

    /**
     * Generate multi-page PDF
     */
    private function generateMultiPagePdf(CertificateTemplate $template, array $allPages)
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
            'pages' => $allPages,
            'template_base64' => $imageBase64,
            'field_positions' => $template->field_positions,
        ];

        // Use a special multi-page template
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

        return $pdf->output();
    }

    /**
     * Get template
     */
    public function getTemplate($templateId = null)
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
     * Create or update certificate record
     */
    private function createOrUpdateSertifikatRecord(WakafBatch $wakafBatch, CertificateTemplate $template, array $options = [])
    {
        // No file cleanup needed - certificates are generated on-demand

        return Sertifikat::updateOrCreate(
            ['wakaf_batch_id' => $wakafBatch->id],
            [
                'donatur_id' => null, // Batch certificates don't have direct donatur_id
                'template_used' => $template->slug ?? 'default',
                'template_id' => $template->id ?? null,
                'is_consolidated' => false, // Batch certificates are not consolidated certificates
                'generated_by' => auth()->id() ?? 1,
                'generated_at' => now(),
            ]
        );
    }

    /**
     * Check if batch is ready for certificate generation
     */
    public function isBatchReadyForCertificate(WakafBatch $wakafBatch)
    {
        // Always ready if batch has donatur data
        // Remove status dependency - can generate anytime after donation input
        return $wakafBatch->donatur && $wakafBatch->total_quran > 0;
    }

    /**
     * Get batches ready for certificate generation
     */
    public function getBatchesReadyForCertificate()
    {
        // First, create missing WakafBatches for donatur that don't have them
        $this->createMissingWakafBatches();

        return WakafBatch::with(['donatur', 'jenisQuran'])
            ->whereDoesntHave('sertifikat')
            ->whereHas('donatur')
            ->where('total_quran', '>', 0)
            ->get()
            ->filter(function ($batch) {
                return $this->isBatchReadyForCertificate($batch);
            });
    }

    /**
     * Create missing WakafBatches for donatur that don't have them yet
     */
    private function createMissingWakafBatches()
    {
        // Find all donatur that have donations and check if they have complete batches
        $donatursNeedingBatches = \App\Models\Donatur::where(function ($query) {
            $query->where('total_a5_count', '>', 0)
                ->orWhere('total_a6_count', '>', 0)
                ->orWhere('total_iqra_count', '>', 0);
        })
            ->get();

        foreach ($donatursNeedingBatches as $donatur) {
            $this->createMissingBatchesForDonatur($donatur);
        }
    }

    /**
     * Create missing batches for a specific donatur
     * Create ONE consolidated batch per donatur containing ALL wakif items
     */
    private function createMissingBatchesForDonatur($donatur)
    {
        $existingBatches = $donatur->wakafBatches()->get();

        // Skip if donatur already has a batch (avoid duplicates)
        if ($existingBatches->isNotEmpty()) {
            return;
        }

        // Get all wakif items for this donatur to calculate correct totals
        $allWakifItems = WakafItem::where('donatur_id', $donatur->id)
            ->whereNotNull('wakif_name')
            ->where('wakif_name', '!=', '')
            ->orderBy('global_sequence')
            ->get();

        // Calculate total mushaf count from all wakif items
        $totalMushaf = $allWakifItems->count();

        // If no wakif items, use donatur total counts as fallback
        if ($totalMushaf == 0) {
            $totalMushaf = ($donatur->total_a5_count ?? 0) +
                          ($donatur->total_a6_count ?? 0) +
                          ($donatur->total_iqra_count ?? 0);
        }

        if ($totalMushaf > 0) {
            // Use A5 as default jenis quran (can be mixed types in consolidated batch)
            $jenisQuran = \App\Models\JenisQuran::where('kode_jenis', 'A5')->first();
            if ($jenisQuran) {
                $this->createWakafBatch($donatur, $jenisQuran, $totalMushaf, 1);

                Log::info('Created consolidated batch for donatur', [
                    'donatur_id' => $donatur->id,
                    'donatur_name' => $donatur->nama_donatur,
                    'total_mushaf' => $totalMushaf,
                    'wakif_items_count' => $allWakifItems->count(),
                    'unique_wakif_names' => $allWakifItems->pluck('wakif_name')->unique()->toArray(),
                ]);
            }
        }
    }

    /**
     * Create a WakafBatch for donatur
     */
    private function createWakafBatch($donatur, $jenisQuran, $totalQuran, $sequence)
    {
        $batchCode = 'WB-'.date('Y').'-'.str_pad(\App\Models\WakafBatch::count() + 1, 5, '0', STR_PAD_LEFT);

        return \App\Models\WakafBatch::create([
            'batch_code' => $batchCode,
            'donatur_id' => $donatur->id,
            'jenis_quran_id' => $jenisQuran->id,
            'total_quran' => $totalQuran,
            'tanggal_wakaf' => $donatur->donation_date ?? now(),
            'status' => 'pending_distribution', // Use proper status from WakafBatch
            'created_by' => auth()->id() ?? 1,
            'catatan' => 'Consolidated batch for all wakif names - Auto-generated from donation data',
        ]);
    }

    /**
     * Get wakif name for a batch (for display purposes)
     */
    public function getWakifNameForBatch(WakafBatch $wakafBatch)
    {
        // First try to get from wakaf items
        $wakifItems = $this->getWakifItemsForBatch($wakafBatch);

        if ($wakifItems->isNotEmpty()) {
            // Get the first wakif name (since we group by name later)
            $firstWakifName = $wakifItems->first()->wakif_name;
            if ($firstWakifName) {
                return $firstWakifName;
            }
        }

        // Fallback to donatur name
        return $wakafBatch->donatur->nama_donatur;
    }
}
