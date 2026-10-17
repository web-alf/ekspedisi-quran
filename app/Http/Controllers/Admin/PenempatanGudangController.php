<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PenempatanGudangRequest;
use App\Models\Gudang;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Penempatan Gudang — menentukan akun SPV/staf gudang bekerja di Semarang atau Boyolali.
 *
 * Kenapa penting: lokasi mushaf (`pengiriman.gudang_id`) diturunkan dari gudang AKUN yang
 * memprosesnya (lihat Pengiriman::updateStatus dan DailyPackingTask::booted). Akun tanpa
 * gudang = mushaf yang ia kerjakan tidak pernah punya lokasi.
 *
 * Pindah gudang hanya mengubah pekerjaan BERIKUTNYA. Lokasi mushaf/kerdus yang sudah
 * tercatat tidak ikut pindah — itu tetap lewat dokumen Perpindahan Mushaf.
 *
 * Wewenang: super-admin dan manager gudang (bukan SPV) — User::bolehAturPenempatanGudang().
 */
class PenempatanGudangController extends Controller
{
    /** Peran yang ditempatkan di gudang. */
    private const PERAN_GUDANG = [RoleEnum::SPV_GUDANG->value, RoleEnum::WAREHOUSE->value];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->bolehAturPenempatanGudang(), 403);

        $akun = User::role(self::PERAN_GUDANG)
            ->with(['roles:id,name', 'gudang:id,kode,nama'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'gudang_id', 'is_active'])
            ->map(function (User $u) {
                // Urutan peran: SPV → Manager Gudang (PJ; di produksi ia juga memegang role
                // warehouse) → staf.
                $urutan = match (true) {
                    $u->hasRole(RoleEnum::SPV_GUDANG->value) => 0,
                    $u->hasRole(RoleEnum::MANAGER_REPACKING->value) => 1,
                    default => 2,
                };

                return [
                    'id' => $u->id,
                    'nama' => $u->name,
                    'email' => $u->email,
                    'peran' => ['SPV Gudang', 'Manager Gudang', 'Staf Gudang'][$urutan],
                    'urutan_peran' => $urutan,
                    'adalah_spv' => $urutan === 0,
                    'aktif' => (bool) $u->is_active,
                    'gudang_id' => $u->gudang_id,
                    'gudang' => $u->gudang?->nama,
                ];
            })
            ->sortBy(fn (array $a) => [$a['urutan_peran'], mb_strtolower($a['nama'])])
            ->values();

        return Inertia::render('Admin/PenempatanGudang/Index', [
            'akun' => $akun,
            'gudangList' => Gudang::where('is_active', true)->orderBy('id')->get(['id', 'kode', 'nama']),
        ]);
    }

    public function update(PenempatanGudangRequest $request, User $user): RedirectResponse
    {
        // Hanya akun gudang yang ditempatkan di sini — bukan admin, CS, kurir, dsb.
        abort_unless($user->hasAnyRole(self::PERAN_GUDANG), 404);

        $lama = $user->gudang?->nama;
        $lamaId = $user->gudang_id;
        $baru = $request->validated('gudang_id');

        $user->forceFill(['gudang_id' => $baru])->save();

        Log::info('Penempatan gudang diubah', [
            'oleh' => $request->user()->id,
            'akun' => $user->id,
            'dari' => $lamaId,
            'ke' => $baru,
        ]);

        $namaBaru = $baru ? Gudang::whereKey($baru)->value('nama') : 'tanpa gudang';

        return back()->with('success', "{$user->name} dipindahkan dari ".($lama ?? 'tanpa gudang')." ke {$namaBaru}.");
    }
}
