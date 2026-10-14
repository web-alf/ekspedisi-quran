<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\PindaiKerdusLog;
use App\Services\PindaiKerdusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint pencatat pindai kerdus (sisi distribusi).
 *
 * Panelnya TIDAK punya halaman sendiri: ia menumpang halaman kerja muatan
 * (`/admin/muatan`), karena di situlah sisi distribusi bekerja — keputusan pemilik
 * sistem. Prop untuk panel itu dibangun `PindaiKerdusService`, yang juga memegang
 * aturan angkanya, supaya endpoint dan panel memakai satu implementasi.
 *
 * Dua jalur masuk yang keduanya sah: `qr` (kamera) dan `manual` (diketik petugas).
 * Kerdus yang belum terdaftar pun tercatat asalkan jenis & jumlah eksnya diisi.
 */
class PindaiKerdusController extends Controller
{
    public function __construct()
    {
        // Lapis kedua di samping middleware rute: hanya pemegang izin
        // distribusi.pindai-kerdus yang boleh mencatat.
        $this->middleware('permission:'.PermissionEnum::DISTRIBUSI_PINDAI_KERDUS->value);
    }

    /**
     * Catat satu pindai kerdus.
     */
    public function store(Request $request, PindaiKerdusService $pindai): JsonResponse
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:120'],
            'metode' => ['nullable', 'in:'.PindaiKerdusLog::METODE_QR.','.PindaiKerdusLog::METODE_MANUAL],
            // Dua kolom di bawah hanya dipakai bila kerdusnya TIDAK terdaftar.
            'jenis_quran_id' => ['nullable', 'integer', 'exists:jenis_quran,id'],
            'jumlah_mushaf' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $hasil = $pindai->catat($request->user(), $validated);

        return response()->json($hasil['data'], $hasil['status']);
    }
}
