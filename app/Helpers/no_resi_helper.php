<?php

/**
 * 🚨 EMERGENCY HELPER - Auto-load di AppServiceProvider
 * 
 * Tambahkan di AppServiceProvider boot() method:
 * require_once app_path('Helpers/no_resi_helper.php');
 */

if (!function_exists('generateNoResiHelper')) {
    function generateNoResiHelper()
    {
        $tahun = date('Y');
        $prefix = "EQ-{$tahun}-";
        
        // Get last resi number
        $lastResi = \DB::table('pengiriman')
            ->where('no_resi', 'LIKE', $prefix . '%')
            ->orderBy('no_resi', 'DESC')
            ->first();
        
        if ($lastResi) {
            $lastNumber = (int) substr($lastResi->no_resi, -5);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        return $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('logNoResiHelper')) {
    function logNoResiHelper()
    {
        \Log::info('No Resi Helper loaded', [
            'timestamp' => now(),
            'sample_resi' => generateNoResiHelper()
        ]);
    }
}
