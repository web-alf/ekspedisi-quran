<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MushafRequest;

class MapController extends Controller
{
    /**
     * Get map data for distribusi peta
     */
    public function getMapData()
    {
        // Get completed mushaf requests with coordinates
        // ✅ FIX: Pakai satu rumus (accessor model) — kolom pecahan & penanda wajib
        // ikut di-select karena accessor menghitung dari kolom tersebut.
        $mapData = MushafRequest::where('status', 'completed')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'nama_lembaga', 'provinsi', 'kota_kabupaten', 'latitude', 'longitude',
                'jumlah_mushaf', 'jumlah_mushaf_a5', 'jumlah_mushaf_a6', 'jumlah_iqra',
                'jumlah_mushaf_approved', 'jumlah_mushaf_a5_approved', 'jumlah_mushaf_a6_approved', 'jumlah_iqra_approved')
            ->get()
            ->map(function ($item) {
                // Pakai accessor model: satu rumus untuk seluruh aplikasi
                return [
                    'id' => $item->id,
                    'nama_lembaga' => $item->nama_lembaga,
                    'provinsi' => $item->provinsi,
                    'kota_kabupaten' => $item->kota_kabupaten,
                    'lat' => (float) $item->latitude,
                    'lng' => (float) $item->longitude,
                    'status' => 'completed',
                    'jumlah_mushaf' => $item->approved_breakdown['total'],
                ];
            });

        return response()->json($mapData);
    }
}
