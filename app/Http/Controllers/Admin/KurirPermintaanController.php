<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MushafRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Data calon permintaan mushaf yang sudah DISETUJUI — sudut pandang kurir.
 *
 * Ini daftar tujuan yang sudah lolos persetujuan tetapi belum terkirim, dipakai
 * kurir untuk tahu ke mana barang akan dibawa. Kurir TIDAK diberi tombol
 * menyetujui/menolak: persetujuan adalah wewenang customer-service, dan kurir
 * hanya membaca.
 *
 * Hanya status `approved` yang ditampilkan — `processed`/`completed` sudah jadi
 * resi, `pending`/`reviewed` belum boleh diantar.
 */
class KurirPermintaanController extends Controller
{
    public function index(Request $request): Response
    {
        $query = MushafRequest::query()
            ->where('status', 'approved')
            ->with(['pengiriman:id,no_resi,status_id', 'pengiriman.status:id,nama,slug,warna']);

        if ($request->filled('search')) {
            $cari = $request->string('search')->toString();
            $query->where(function ($q) use ($cari): void {
                $q->where('nama_lembaga', 'like', "%{$cari}%")
                    ->orWhere('nama_pengurus_1', 'like', "%{$cari}%")
                    ->orWhere('no_request', 'like', "%{$cari}%")
                    ->orWhere('kota_kabupaten', 'like', "%{$cari}%");
            });
        }

        if ($request->filled('kategori')) {
            $query->where('kategori_lembaga', $request->string('kategori')->toString());
        }

        if ($request->filled('provinsi')) {
            $query->where('provinsi', $request->string('provinsi')->toString());
        }

        $perPage = $this->ukuranHalaman($request);

        $permintaan = $query->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $permintaan->through(fn (MushafRequest $r) => [
            'id' => $r->id,
            'no_request' => $r->no_request,
            'nama_lembaga' => $r->nama_lembaga,
            'nama_pengurus' => $r->nama_pengurus_1,
            'kategori_lembaga' => $r->kategori_lembaga,
            'no_hp' => $r->whatsapp_pengurus_1,
            'provinsi' => $r->provinsi,
            'kota_kabupaten' => $r->kota_kabupaten,
            'kecamatan' => $r->kecamatan,
            'kelurahan_desa' => $r->kelurahan_desa,
            'alamat_lengkap' => $r->alamat_lengkap,
            'latitude' => $r->latitude,
            'longitude' => $r->longitude,
            'link_gmaps' => $r->link_gmaps,
            'approved_at' => $r->approved_at?->format('Y-m-d'),
            'jumlah_disetujui' => $r->total_mushaf_approved,
            'rincian' => $r->approved_breakdown,
            'jumlah_permintaan' => $r->total_mushaf,
            'pengiriman' => $r->pengiriman ? [
                'no_resi' => $r->pengiriman->no_resi,
                'status' => $r->pengiriman->status?->nama,
                'status_slug' => $r->pengiriman->status?->slug,
                'status_warna' => $r->pengiriman->status?->warna,
            ] : null,
        ]);

        return Inertia::render('Admin/Kurir/PermintaanDisetujui', [
            'permintaan' => $permintaan,
            'filters' => $request->only(['search', 'kategori', 'provinsi', 'per_page']),
            'kategoriList' => MushafRequest::query()
                ->where('status', 'approved')
                ->whereNotNull('kategori_lembaga')
                ->distinct()
                ->orderBy('kategori_lembaga')
                ->pluck('kategori_lembaga')
                ->all(),
            'provinsiList' => MushafRequest::query()
                ->where('status', 'approved')
                ->whereNotNull('provinsi')
                ->distinct()
                ->orderBy('provinsi')
                ->pluck('provinsi')
                ->all(),
            'ringkasan' => [
                'total_permintaan' => MushafRequest::where('status', 'approved')->count(),
                'total_mushaf' => (int) MushafRequest::where('status', 'approved')
                    ->selectRaw('COALESCE(SUM(jumlah_mushaf_approved), 0) as total')
                    ->value('total'),
                'belum_ada_resi' => MushafRequest::where('status', 'approved')
                    ->whereNull('pengiriman_id')
                    ->count(),
            ],
        ]);
    }

    /**
     * Ukuran halaman wajib di-whitelist: nilai dari klien tidak boleh langsung
     * dipakai sebagai limit query.
     */
    private function ukuranHalaman(Request $request): int
    {
        $diminta = (int) $request->input('per_page', 15);

        return in_array($diminta, [10, 15, 25, 50, 100], true) ? $diminta : 15;
    }
}
