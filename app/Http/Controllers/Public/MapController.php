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
        // ✅ FIX: Use approved quantities with fallback to requested
        $mapData = MushafRequest::where('status', 'completed')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'nama_lembaga', 'provinsi', 'kota_kabupaten', 'latitude', 'longitude',
                'jumlah_mushaf', 'jumlah_iqra', 'jumlah_mushaf_approved', 'jumlah_iqra_approved')
            ->get()
            ->map(function ($item) {
                // Use approved quantities with fallback to requested
                $approvedMushaf = $item->jumlah_mushaf_approved ?? $item->jumlah_mushaf;
                $approvedIqra = $item->jumlah_iqra_approved ?? $item->jumlah_iqra;

                return [
                    'id' => $item->id,
                    'nama_lembaga' => $item->nama_lembaga,
                    'provinsi' => $item->provinsi,
                    'kota_kabupaten' => $item->kota_kabupaten,
                    'lat' => (float) $item->latitude,
                    'lng' => (float) $item->longitude,
                    'status' => 'completed',
                    'jumlah_mushaf' => $approvedMushaf + $approvedIqra,
                ];
            });

        return response()->json($mapData);
    }
}
