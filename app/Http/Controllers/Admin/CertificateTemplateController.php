<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateTemplate;
use App\Models\WakafBatch;
use App\Services\CertificateService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CertificateTemplateController extends Controller
{
    protected $certificateService;

    public function __construct(CertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
        $this->authorizeResource(CertificateTemplate::class, 'certificateTemplate');
    }

    /**
     * Display templates list
     */
    public function index(Request $request)
    {
        $query = CertificateTemplate::with('creator')
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $templates = $query->paginate(20)->appends($request->query());

        return Inertia::render('Admin/CertificateTemplates/Index', [
            'templates' => $templates,
            'filters' => $request->only(['search', 'status']),
            'stats' => [
                'total' => CertificateTemplate::count(),
                'active' => CertificateTemplate::active()->count(),
                'default' => CertificateTemplate::default()->count(),
            ],
            'availableFields' => CertificateTemplate::getAvailableFields(),
            'defaultPositions' => CertificateTemplate::getDefaultFieldPositions(),
        ]);
    }

    /**
     * Show create template form
     */
    public function create()
    {
        return Inertia::render('Admin/CertificateTemplates/Create', [
            'availableFields' => CertificateTemplate::getAvailableFields(),
            'defaultPositions' => CertificateTemplate::getDefaultFieldPositions(),
        ]);
    }

    /**
     * Store new template
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'template_file' => 'required|image|mimes:png,jpg,jpeg|max:10240',
            'field_positions' => 'required|array',
            'is_default' => 'boolean',
        ]);

        try {
            $templateFile = $request->file('template_file');

            // Sanitize filename for security
            $originalName = $templateFile->getClientOriginalName();
            $safeBaseName = \Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
            $extension = $templateFile->getClientOriginalExtension();

            // Validate file extension
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            if (! in_array(strtolower($extension), $allowedExtensions)) {
                return back()->withErrors(['template_file' => 'File type not allowed. Only JPG, JPEG, PNG, WEBP are allowed.']);
            }

            // Validate MIME type
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (! in_array($templateFile->getMimeType(), $allowedMimes)) {
                return back()->withErrors(['template_file' => 'Invalid file type detected.']);
            }

            $filename = 'template_'.time().'_'.$safeBaseName.'.'.$extension;
            $templatePath = $templateFile->storeAs('certificate-templates', $filename, 'public');

            $imagePath = storage_path('app/public/'.$templatePath);

            if (! file_exists($imagePath)) {
                \Log::error('File not found at: '.$imagePath);

                return back()->withErrors(['template_file' => 'File upload gagal, tidak dapat menemukan file']);
            }

            $imageInfo = getimagesize($imagePath);

            if (! $imageInfo) {
                return back()->withErrors(['template_file' => 'File bukan gambar yang valid']);
            }

            $template = CertificateTemplate::create([
                'name' => $request->name,
                'description' => $request->description,
                'template_file_path' => $templatePath,
                'field_positions' => $request->field_positions,
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'is_active' => true,
                'is_default' => $request->boolean('is_default'),
                'created_by' => auth()->id(),
            ]);

            return redirect()->route('admin.certificate-templates.index')
                ->with('success', 'Template berhasil dibuat');

        } catch (Exception $e) {
            \Log::error('Certificate template creation failed: '.$e->getMessage());

            return back()->withErrors(['error' => 'Gagal membuat template: '.$e->getMessage()]);
        }
    }

    /**
     * Show template details
     */
    public function show(CertificateTemplate $certificateTemplate)
    {
        $certificateTemplate->load('creator');

        // Get sample wakaf batches for testing certificate generation
        $wakafBatches = WakafBatch::with('donatur')
            ->whereHas('donatur')
            ->limit(10)
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'batch_code' => $batch->batch_code,
                    'wakif_name' => $batch->donatur->nama_donatur,
                    'total_quran' => $batch->total_quran,
                    'has_certificate' => $batch->sertifikat !== null,
                ];
            });

        // Get current Hijri date for accurate preview
        $currentHijriDate = 'Semarang, [Tanggal Hijriah Saat Ini]';
        try {
            $currentHijriDate = 'Semarang, '.\App\Helpers\HijriHelper::getCurrentHijriDate();
        } catch (\Exception $e) {
            \Log::warning('Failed to get current Hijri date for template preview: '.$e->getMessage());
        }

        return Inertia::render('Admin/CertificateTemplates/Show', [
            'template' => $certificateTemplate,
            'availableFields' => CertificateTemplate::getAvailableFields(),
            'imageInfo' => $certificateTemplate->getTemplateImageInfo(),
            'wakafBatches' => $wakafBatches,
            'currentHijriDate' => $currentHijriDate,
        ]);
    }

    /**
     * Show edit template form
     */
    public function edit(CertificateTemplate $certificateTemplate)
    {
        return Inertia::render('Admin/CertificateTemplates/Edit', [
            'template' => $certificateTemplate,
            'availableFields' => CertificateTemplate::getAvailableFields(),
        ]);
    }

    /**
     * Update template
     */
    public function update(Request $request, CertificateTemplate $certificateTemplate)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'template_file' => 'nullable|image|mimes:png,jpg,jpeg|max:10240',
            'field_positions' => 'required|array',
            'field_positions.*' => 'array',
            'field_positions.*.x' => 'integer|min:0',
            'field_positions.*.y' => 'integer|min:0',
            'field_positions.*.font_size' => 'integer|min:8|max:72',
            'field_positions.*.color' => 'string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        try {
            $updateData = [
                'name' => $request->name,
                'description' => $request->description,
                'field_positions' => $request->field_positions,
                'is_active' => $request->boolean('is_active'),
                'is_default' => $request->boolean('is_default'),
            ];

            if ($request->hasFile('template_file')) {
                if ($certificateTemplate->template_file_path) {
                    Storage::disk('public')->delete($certificateTemplate->template_file_path);
                }

                $templateFile = $request->file('template_file');

                // Sanitize filename for security
                $originalName = $templateFile->getClientOriginalName();
                $safeBaseName = \Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
                $extension = $templateFile->getClientOriginalExtension();

                // Validate file extension
                $allowedExtensions = ['jpg', 'jpeg', 'png'];
                if (! in_array(strtolower($extension), $allowedExtensions)) {
                    return back()->withErrors(['template_file' => 'File type not allowed. Only JPG, JPEG, PNG are allowed.']);
                }

                $filename = 'template_'.time().'_'.$safeBaseName.'.'.$extension;
                $templatePath = $templateFile->storeAs('certificate-templates', $filename, 'public');

                $imagePath = storage_path('app/public/'.$templatePath);
                $imageInfo = getimagesize($imagePath);

                $updateData['template_file_path'] = $templatePath;
                $updateData['width'] = $imageInfo[0];
                $updateData['height'] = $imageInfo[1];
            }

            $certificateTemplate->update($updateData);

            return redirect()->route('admin.certificate-templates.index')
                ->with('success', 'Template berhasil diupdate');

        } catch (Exception $e) {
            \Log::error('Certificate template update failed: '.$e->getMessage());

            return back()->withErrors(['error' => 'Gagal mengupdate template: '.$e->getMessage()]);
        }
    }

    /**
     * Delete template
     */
    public function destroy(CertificateTemplate $certificateTemplate)
    {
        try {
            if ($certificateTemplate->certificates()->count() > 0) {
                return back()->withErrors(['error' => 'Template tidak dapat dihapus karena sedang digunakan']);
            }

            if ($certificateTemplate->template_file_path) {
                Storage::delete($certificateTemplate->template_file_path);
            }

            $certificateTemplate->delete();

            return back()->with('success', 'Template berhasil dihapus');

        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Gagal menghapus template: '.$e->getMessage()]);
        }
    }

    /**
     * Generate certificate using this template
     */
    public function generateCertificate(Request $request, CertificateTemplate $certificateTemplate)
    {
        $request->validate([
            'wakaf_batch_id' => 'required|exists:wakaf_batch,id',
            'regenerate' => 'boolean',
        ]);

        try {
            $wakafBatch = WakafBatch::with('donatur')->findOrFail($request->wakaf_batch_id);

            $options = [
                'template_id' => $certificateTemplate->id,
                'regenerate' => $request->boolean('regenerate', false),
            ];

            $sertifikat = $this->certificateService->generateCertificateForBatch($wakafBatch, $options);

            return response()->json([
                'success' => true,
                'message' => 'Sertifikat berhasil dibuat',
                'data' => [
                    'certificate_id' => $sertifikat->id,
                    'certificate_number' => $sertifikat->nomor_sertifikat,
                    'download_url' => route('admin.certificates.download', $sertifikat->id),
                    'view_url' => route('admin.certificates.view', $sertifikat->id),
                ],
            ]);

        } catch (Exception $e) {
            \Log::error('Certificate generation failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Preview template
     */
    public function preview(CertificateTemplate $certificateTemplate)
    {
        if (! $certificateTemplate->templateFileExists()) {
            abort(404, 'Template file tidak ditemukan');
        }

        $filePath = $certificateTemplate->getFullTemplatePath();
        $fileContent = file_get_contents($filePath);

        return response($fileContent, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="template-preview.png"',
        ]);
    }

    /**
     * Download template file
     */
    public function download(CertificateTemplate $certificateTemplate)
    {
        if (! $certificateTemplate->templateFileExists()) {
            abort(404, 'Template file tidak ditemukan');
        }

        return response()->download(
            $certificateTemplate->getFullTemplatePath(),
            $certificateTemplate->name.'.png'
        );
    }

    /**
     * Set as default template
     */
    public function setDefault(CertificateTemplate $certificateTemplate)
    {
        $this->authorize('setDefault', $certificateTemplate);
        try {
            CertificateTemplate::where('is_default', true)->update(['is_default' => false]);
            $certificateTemplate->update(['is_default' => true]);

            return back()->with('success', 'Template berhasil diset sebagai default');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Gagal set default template: '.$e->getMessage()]);
        }
    }

    /**
     * Toggle template status
     */
    public function toggleStatus(CertificateTemplate $certificateTemplate)
    {
        $this->authorize('toggle', $certificateTemplate);
        try {
            $certificateTemplate->update(['is_active' => ! $certificateTemplate->is_active]);
            $status = $certificateTemplate->is_active ? 'diaktifkan' : 'dinonaktifkan';

            return back()->with('success', "Template berhasil {$status}");
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengubah status template: '.$e->getMessage()]);
        }
    }

    /**
     * Update field position via AJAX
     */
    public function updateFieldPosition(Request $request, CertificateTemplate $certificateTemplate)
    {
        $request->validate([
            'field_name' => 'required|string',
            'x' => 'required|integer|min:0',
            'y' => 'required|integer|min:0',
            'font_size' => 'nullable|integer|min:8|max:72',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        try {
            $certificateTemplate->updateFieldPosition(
                $request->field_name,
                $request->x,
                $request->y,
                $request->font_size,
                $request->color
            );

            return response()->json([
                'success' => true,
                'message' => 'Posisi field berhasil diupdate',
                'data' => $certificateTemplate->getFieldPosition($request->field_name),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal update posisi: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update all field positions via AJAX
     */
    public function updateAllPositions(Request $request, CertificateTemplate $certificateTemplate)
    {
        $request->validate([
            'field_positions' => 'required|array',
        ]);

        // Validate each field position individually
        foreach ($request->field_positions as $fieldName => $position) {
            $request->validate([
                "field_positions.{$fieldName}.x" => 'required|integer',
                "field_positions.{$fieldName}.y" => 'required|integer',
                "field_positions.{$fieldName}.font_size" => 'nullable|integer|min:1',
                "field_positions.{$fieldName}.color" => 'nullable|string',
            ]);
        }

        try {
            // Log the incoming data for debugging
            \Log::info('Updating field positions', [
                'template_id' => $certificateTemplate->id,
                'incoming_data' => $request->field_positions,
                'current_positions' => $certificateTemplate->field_positions,
            ]);

            // Update field_positions column with new data
            $certificateTemplate->field_positions = $request->field_positions;
            $certificateTemplate->save();

            // Verify the save worked
            $certificateTemplate->refresh();

            \Log::info('Field positions updated', [
                'template_id' => $certificateTemplate->id,
                'final_positions' => $certificateTemplate->field_positions,
            ]);

            // Return back with updated template data for Inertia
            return back()->with([
                'success' => 'Posisi field template sertifikat berhasil diperbarui',
                'template' => $certificateTemplate,
            ]);
        } catch (Exception $e) {
            return back()->withErrors([
                'error' => 'Terjadi kesalahan saat menyimpan posisi field. Silakan coba lagi.',
            ]);
        }
    }

    /**
     * Generate preview image with sample data overlaid on template
     */
    public function previewWithSampleData(CertificateTemplate $certificateTemplate)
    {
        try {
            // Use current date for more accurate preview
            $currentHijriDate = \App\Helpers\HijriHelper::getCurrentHijriDate();

            $sampleData = [
                'wakif_name' => 'Bapak Ahmad Sulaiman',
                'hijri_date' => 'Semarang, '.$currentHijriDate,
            ];

            if (! $certificateTemplate->templateFileExists()) {
                return response()->json(['error' => 'Template file not found'], 404);
            }

            $templatePath = $certificateTemplate->getFullTemplatePath();
            $image = imagecreatefrompng($templatePath);

            if (! $image) {
                return response()->json(['error' => 'Unable to load template image'], 500);
            }

            $fontPath = public_path('fonts/arial.ttf');
            $useCustomFont = file_exists($fontPath);

            foreach ($certificateTemplate->field_positions as $fieldKey => $position) {
                if (isset($sampleData[$fieldKey])) {
                    $text = $sampleData[$fieldKey];
                    $x = $position['x'] ?? 0;
                    $y = $position['y'] ?? 0;
                    $fontSize = $position['font_size'] ?? 12;
                    $color = $position['color'] ?? '#000000';

                    $color = ltrim($color, '#');
                    $r = hexdec(substr($color, 0, 2));
                    $g = hexdec(substr($color, 2, 2));
                    $b = hexdec(substr($color, 4, 2));
                    $textColor = imagecolorallocate($image, $r, $g, $b);

                    if ($useCustomFont) {
                        imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontPath, $text);
                    } else {
                        $builtInSize = max(1, min(5, intval($fontSize / 4)));
                        imagestring($image, $builtInSize, $x, $y, $text, $textColor);
                    }
                }
            }

            header('Content-Type: image/png');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            imagepng($image);
            imagedestroy($image);

        } catch (Exception $e) {
            return response()->json(['error' => 'Preview generation failed: '.$e->getMessage()], 500);
        }
    }
}
