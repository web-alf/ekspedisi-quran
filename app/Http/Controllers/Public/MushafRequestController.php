<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MushafRequest;
use App\Services\Cache\DashboardCacheService;
use App\Services\FileStorageService;
use App\Traits\HandlesImageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MushafRequestController extends Controller
{
    use HandlesImageUpload;

    protected FileStorageService $fileStorageService;

    protected DashboardCacheService $dashboardCache;

    public function __construct(
        FileStorageService $fileStorageService,
        DashboardCacheService $dashboardCache
    ) {
        $this->fileStorageService = $fileStorageService;
        $this->dashboardCache = $dashboardCache;
    }

    /**
     * Show the form for creating a new mushaf request
     */
    public function index()
    {
        // Get settings for consistent layout
        $settings = \App\Models\Setting::where('is_public', true)
            ->where('is_active', true)
            ->whereIn('group', ['landing', 'contact', 'social', 'general'])
            ->pluck('value', 'key');

        return Inertia::render('Public/MushafRequest', [
            'pageTitle' => 'Formulir Permintaan Mushaf Al-Qur\'an',
            'pageDescription' => 'Ajukan permintaan mushaf Al-Qur\'an gratis untuk lembaga Anda',
            'settings' => $settings,
        ]);
    }

    /**
     * Store a newly created mushaf request
     */
    public function store(Request $request)
    {
        $request->validate([
            // Data Lembaga
            'nama_lembaga' => ['required', 'string', 'max:255'],
            'kategori_lembaga' => [
                'required',
                'string',
                Rule::in(array_merge(
                    MushafRequest::KATEGORI_LEMBAGA['PENDIDIKAN'],
                    MushafRequest::KATEGORI_LEMBAGA['KOMUNITAS'],
                    MushafRequest::KATEGORI_LEMBAGA['SOSIAL_PEMERINTAHAN'],
                    MushafRequest::KATEGORI_LEMBAGA['PENERIMA_KHUSUS']
                )),
            ],
            'alamat_lengkap' => ['required', 'string', 'max:1000'],

            // Koordinat Lokasi
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],

            // Detailed address fields (optional)
            'provinsi' => ['nullable', 'string', 'max:255'],
            'provinsi_id' => ['nullable', 'string', 'max:10'],
            'kota_kabupaten' => ['nullable', 'string', 'max:255'],
            'kota_kabupaten_id' => ['nullable', 'string', 'max:10'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'kecamatan_id' => ['nullable', 'string', 'max:10'],
            'kelurahan_desa' => ['nullable', 'string', 'max:255'],
            'kelurahan_desa_id' => ['nullable', 'string', 'max:10'],
            'kode_pos' => ['nullable', 'string', 'regex:/^[0-9]{5}$/', 'max:5'],
            'alamat_detail' => ['nullable', 'string', 'max:500'],

            // Pengurus 1
            'nama_pengurus_1' => ['required', 'string', 'max:255'],
            'jabatan_pengurus_1' => ['required', 'string', 'max:255'],
            'whatsapp_pengurus_1' => ['required', 'string', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,9}$/'],

            // Pengurus 2
            'nama_pengurus_2' => ['required', 'string', 'max:255'],
            'jabatan_pengurus_2' => ['required', 'string', 'max:255'],
            'whatsapp_pengurus_2' => ['required', 'string', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,9}$/'],

            // Detail Permintaan
            'urgensi_request' => ['required', 'string', 'max:2000'],
            'jumlah_mushaf' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'jumlah_mushaf_a5' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'jumlah_mushaf_a6' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'jumlah_iqra' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'jenis_mushaf_diminta' => ['required', 'array', 'min:1'],
            'jenis_mushaf_diminta.*' => ['in:A5,A6,IQRA'],

            // Sumber Info
            'sumber_info' => ['required', 'string', 'max:255'],

            // File uploads with adjusted validation
            'foto_santri' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:2048', // 2MB
            ],
            'foto_lembaga' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:2048', // 2MB
            ],
            'file_nama_santri' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx',
                'max:2048', // 2MB
            ],
        ], [
            // Custom error messages
            'nama_lembaga.required' => 'Nama lembaga wajib diisi',
            'kategori_lembaga.required' => 'Kategori lembaga wajib dipilih',
            'kategori_lembaga.in' => 'Kategori lembaga yang dipilih tidak valid',
            'alamat_lengkap.required' => 'Alamat lengkap wajib diisi',
            'latitude.required' => 'Latitude wajib diisi',
            'latitude.numeric' => 'Latitude harus berupa angka',
            'latitude.between' => 'Latitude harus antara -90 dan 90',
            'longitude.required' => 'Longitude wajib diisi',
            'longitude.numeric' => 'Longitude harus berupa angka',
            'longitude.between' => 'Longitude harus antara -180 dan 180',
            'kode_pos.regex' => 'Kode pos harus terdiri dari 5 digit angka',
            'kode_pos.max' => 'Kode pos maksimal 5 karakter',
            'nama_pengurus_1.required' => 'Nama pengurus 1 wajib diisi',
            'jabatan_pengurus_1.required' => 'Jabatan pengurus 1 wajib diisi',
            'whatsapp_pengurus_1.required' => 'WhatsApp pengurus 1 wajib diisi',
            'whatsapp_pengurus_1.regex' => 'Format nomor WhatsApp pengurus 1 tidak valid',
            'nama_pengurus_2.required' => 'Nama pengurus 2 wajib diisi',
            'jabatan_pengurus_2.required' => 'Jabatan pengurus 2 wajib diisi',
            'whatsapp_pengurus_2.required' => 'WhatsApp pengurus 2 wajib diisi',
            'whatsapp_pengurus_2.regex' => 'Format nomor WhatsApp pengurus 2 tidak valid',
            'urgensi_request.required' => 'Urgensi permintaan wajib diisi',
            'jumlah_mushaf_a5.integer' => 'Jumlah mushaf A5 harus berupa angka',
            'jumlah_mushaf_a5.min' => 'Jumlah mushaf A5 minimal 0',
            'jumlah_mushaf_a5.max' => 'Jumlah mushaf A5 maksimal 1000',
            'jumlah_mushaf_a6.integer' => 'Jumlah mushaf A6 harus berupa angka',
            'jumlah_mushaf_a6.min' => 'Jumlah mushaf A6 minimal 0',
            'jumlah_mushaf_a6.max' => 'Jumlah mushaf A6 maksimal 1000',
            'jumlah_iqra.integer' => 'Jumlah IQRA harus berupa angka',
            'jumlah_iqra.min' => 'Jumlah IQRA minimal 0',
            'jumlah_iqra.max' => 'Jumlah IQRA maksimal 1000',
            'jenis_mushaf_diminta.required' => 'Jenis mushaf yang diminta wajib dipilih',
            'jenis_mushaf_diminta.min' => 'Pilih minimal 1 jenis mushaf',
            'sumber_info.required' => 'Sumber informasi wajib diisi',
            'foto_santri.required' => 'Foto santri wajib diupload',
            'foto_santri.mimes' => 'Foto santri harus dalam format JPG, JPEG, PNG, atau WEBP',
            'foto_santri.max' => 'Ukuran foto santri maksimal 2MB',
            'foto_lembaga.required' => 'Foto lembaga wajib diupload',
            'foto_lembaga.mimes' => 'Foto lembaga harus dalam format JPG, JPEG, PNG, atau WEBP',
            'foto_lembaga.max' => 'Ukuran foto lembaga maksimal 2MB',
            'file_nama_santri.required' => 'File nama santri wajib diupload',
            'file_nama_santri.mimes' => 'File nama santri harus dalam format JPG, JPEG, PNG, WEBP, PDF, DOC, DOCX, XLS, atau XLSX',
            'file_nama_santri.max' => 'Ukuran file nama santri maksimal 2MB',
        ]);

        // Custom validation: ensure total mushaf > 0
        $totalMushaf = ($request->jumlah_mushaf ?? 0) +
                      ($request->jumlah_mushaf_a5 ?? 0) +
                      ($request->jumlah_mushaf_a6 ?? 0) +
                      ($request->jumlah_iqra ?? 0);

        if ($totalMushaf <= 0) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['jumlah_mushaf' => 'Total jumlah mushaf yang diminta harus lebih dari 0']);
        }

        // Validate jenis mushaf consistency
        $jenisRequested = $request->jenis_mushaf_diminta;
        $hasA5 = ($request->jumlah_mushaf_a5 ?? 0) > 0;
        $hasA6 = ($request->jumlah_mushaf_a6 ?? 0) > 0;
        $hasIqra = ($request->jumlah_iqra ?? 0) > 0;

        if (in_array('A5', $jenisRequested) && ! $hasA5) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['jumlah_mushaf_a5' => 'Jumlah mushaf A5 harus diisi karena A5 dipilih dalam jenis mushaf']);
        }

        if (in_array('A6', $jenisRequested) && ! $hasA6) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['jumlah_mushaf_a6' => 'Jumlah mushaf A6 harus diisi karena A6 dipilih dalam jenis mushaf']);
        }

        if (in_array('IQRA', $jenisRequested) && ! $hasIqra) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['jumlah_iqra' => 'Jumlah IQRA harus diisi karena IQRA dipilih dalam jenis mushaf']);
        }

        try {
            \DB::beginTransaction();

            // Upload files with optimization
            $fotoSantriPath = $this->uploadOptimizedFile($request->file('foto_santri'), 'mushaf-requests/foto-santri', 'image');
            $fotoLembagaPath = $this->uploadOptimizedFile($request->file('foto_lembaga'), 'mushaf-requests/foto-lembaga', 'image');
            $fileNamaSantriPath = $this->uploadOptimizedFile($request->file('file_nama_santri'), 'mushaf-requests/file-nama-santri', 'document');

            // Extract individual totals for database
            $totalA5 = $request->jumlah_mushaf_a5 ?? 0;
            $totalA6 = $request->jumlah_mushaf_a6 ?? 0;
            $totalIqra = $request->jumlah_iqra ?? 0;

            // Create mushaf request
            $mushafRequest = MushafRequest::create([
                'nama_lembaga' => $request->nama_lembaga,
                'kategori_lembaga' => $request->kategori_lembaga,
                'alamat_lengkap' => $request->alamat_lengkap,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                // Detailed address fields
                'provinsi' => $request->provinsi,
                'provinsi_id' => $request->provinsi_id,
                'kota_kabupaten' => $request->kota_kabupaten,
                'kota_kabupaten_id' => $request->kota_kabupaten_id,
                'kecamatan' => $request->kecamatan,
                'kecamatan_id' => $request->kecamatan_id,
                'kelurahan_desa' => $request->kelurahan_desa,
                'kelurahan_desa_id' => $request->kelurahan_desa_id,
                'kode_pos' => $request->kode_pos,
                'alamat_detail' => $request->alamat_detail,
                'nama_pengurus_1' => $request->nama_pengurus_1,
                'jabatan_pengurus_1' => $request->jabatan_pengurus_1,
                'whatsapp_pengurus_1' => $request->whatsapp_pengurus_1,
                'nama_pengurus_2' => $request->nama_pengurus_2,
                'jabatan_pengurus_2' => $request->jabatan_pengurus_2,
                'whatsapp_pengurus_2' => $request->whatsapp_pengurus_2,
                'urgensi_request' => $request->urgensi_request,
                'jumlah_mushaf' => $totalA5 + $totalA6, // Total mushaf (A5 + A6)
                'jumlah_mushaf_a5' => $totalA5,
                'jumlah_mushaf_a6' => $totalA6,
                'jumlah_iqra' => $totalIqra,
                'jenis_mushaf_diminta' => $request->jenis_mushaf_diminta,
                'sumber_info' => $request->sumber_info,
                'foto_santri_path' => $fotoSantriPath,
                'foto_lembaga_path' => $fotoLembagaPath,
                'file_nama_santri_path' => $fileNamaSantriPath,
                'status' => 'pending',
            ]);

            \DB::commit();

            // ✅ FIX: Invalidate dashboard cache after creating new request
            $this->dashboardCache->invalidate(['dashboard', 'stats', 'activities']);

            return redirect()->route('mushaf-request.success', $mushafRequest->no_request)
                ->with('success', 'Permintaan mushaf berhasil dikirim dengan nomor: '.$mushafRequest->no_request);

        } catch (\Illuminate\Http\Exceptions\PostTooLargeException $e) {
            \DB::rollback();

            return back()->withErrors([
                'error' => '⚠️ Ukuran file melebihi batas maksimum yang diizinkan! Total ukuran file tidak boleh melebihi 6MB. Detail batas ukuran file: Setiap file maksimal 2MB. Silakan kompres atau pilih file yang lebih kecil.',
            ])->withInput();
        } catch (\Exception $e) {
            \DB::rollback();

            // Clean up uploaded files if any (including thumbnails)
            if (isset($fotoSantriPath)) {
                $this->deleteImageWithThumbnail($fotoSantriPath);
            }
            if (isset($fotoLembagaPath)) {
                $this->deleteImageWithThumbnail($fotoLembagaPath);
            }
            if (isset($fileNamaSantriPath)) {
                Storage::disk('public')->delete($fileNamaSantriPath);
            }

            return back()->withErrors([
                'error' => 'Gagal mengirim permintaan: '.$e->getMessage(),
            ])->withInput();
        }
    }

    /**
     * Show success page after successful submission
     */
    public function success($noRequest)
    {
        $mushafRequest = MushafRequest::where('no_request', $noRequest)
            ->with(['reviewer'])
            ->firstOrFail();

        // Ensure fresh status data
        $mushafRequest->makeHidden(['foto_santri_path', 'foto_lembaga_path', 'file_nama_santri_path']);

        // Get settings for consistent layout
        $settings = \App\Models\Setting::where('is_public', true)
            ->where('is_active', true)
            ->whereIn('group', ['landing', 'contact', 'social', 'general'])
            ->pluck('value', 'key');

        return Inertia::render('Public/MushafRequestSuccess', [
            'mushafRequest' => $mushafRequest,
            'pageTitle' => 'Permintaan Berhasil Dikirim',
            'settings' => $settings,
        ]);
    }

    /**
     * Check status of mushaf request
     */
    public function checkStatus(Request $request)
    {
        $request->validate([
            'no_request' => 'required|string',
        ]);

        $mushafRequest = MushafRequest::where('no_request', $request->no_request)->first();

        if (! $mushafRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor permintaan tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'no_request' => $mushafRequest->no_request,
                'nama_lembaga' => $mushafRequest->nama_lembaga,
                'status' => $mushafRequest->status,
                'status_label' => $mushafRequest->status_label,
                'total_mushaf' => $mushafRequest->total_mushaf,
                'created_at' => $mushafRequest->created_at->format('d/m/Y H:i'),
                'catatan_admin' => $mushafRequest->catatan_admin,
            ],
        ]);
    }

    /**
     * Upload file with optimization based on type
     */
    private function uploadOptimizedFile($file, $directory, $type = 'general')
    {
        if (! $file) {
            return null;
        }

        try {
            // Validate file extension against allowed types
            $extension = strtolower($file->getClientOriginalExtension());
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];

            if (! in_array($extension, $allowedExtensions)) {
                throw new \InvalidArgumentException('File type not allowed: '.$extension);
            }

            if ($type === 'image' && in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                // Use optimized image upload
                $options = $this->getOptimizationConfig('testimonial'); // Use testimonial config for smaller images
                $result = $this->uploadOptimizedImage($file, $directory, $options);

                // Log successful optimization
                Log::info('Image optimized during mushaf request upload', [
                    'original_name' => $file->getClientOriginalName(),
                    'optimized_path' => $result,
                    'directory' => $directory,
                ]);

                return $result;
            } else {
                // Use organized file storage for documents
                $uploadResult = $this->fileStorageService->organizeUpload($file, $directory, [
                    'sub_purpose' => 'mushaf-request',
                    'mime_type' => $file->getMimeType(),
                ]);

                return $uploadResult['path'];
            }
        } catch (\Exception $e) {
            Log::error('File upload failed in mushaf request', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
                'directory' => $directory,
                'type' => $type,
            ]);

            // Fallback to basic upload
            return $this->fallbackUpload($file, $directory);
        }
    }

    /**
     * Fallback upload method for when optimization fails
     */
    private function fallbackUpload($file, $directory)
    {
        // Sanitize filename to prevent path traversal attacks
        $originalName = $file->getClientOriginalName();
        $safeBaseName = \Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $extension = $file->getClientOriginalExtension();

        // Generate secure filename
        $filename = time().'_'.$safeBaseName.'.'.$extension;

        $path = $file->storeAs($directory, $filename, 'public');

        Log::info('Fallback upload used for mushaf request', [
            'original_name' => $originalName,
            'stored_path' => $path,
        ]);

        return $path;
    }
}
