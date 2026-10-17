<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SerahTerimaKurirRequest;
use App\Services\SerahTerimaKurirService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pindai serah terima kurir — di lokasi tujuan, atau dialihkan di tengah jalan.
 *
 * Seluruh aturannya ada di SerahTerimaKurirService; controller ini hanya memvalidasi
 * masukan dan meneruskan jawabannya.
 */
class KurirSerahTerimaController extends Controller
{
    /**
     * Langkah 1: uraikan hasil pindai menjadi resi yang akan diserahkan (tanpa menulis).
     */
    public function periksa(Request $request, SerahTerimaKurirService $serahTerima): JsonResponse
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:255'],
            'mode' => ['required', Rule::in([SerahTerimaKurirService::MODE_TUJUAN, SerahTerimaKurirService::MODE_TENGAH_JALAN])],
        ]);

        $hasil = $serahTerima->periksa($request->user(), $validated['kode'], $validated['mode']);

        return response()->json($hasil['data'], $hasil['status']);
    }

    /**
     * Langkah 2: catat serah terima (status resi → "Tiba di Tujuan").
     */
    public function simpan(SerahTerimaKurirRequest $request, SerahTerimaKurirService $serahTerima): JsonResponse
    {
        $hasil = $serahTerima->simpan(
            $request->user(),
            $request->safe()->except('foto'),
            $request->file('foto', [])
        );

        return response()->json($hasil['data'], $hasil['status']);
    }
}
