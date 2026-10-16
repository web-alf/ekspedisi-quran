<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix foto_dokumentasi field that may have corrupt data
        
        // Get all tracking history with non-null foto_dokumentasi
        $histories = DB::table('tracking_history')
            ->whereNotNull('foto_dokumentasi')
            ->get();
            
        foreach ($histories as $history) {
            $fotoDokumentasi = $history->foto_dokumentasi;
            
            // Skip if already null
            if (!$fotoDokumentasi) {
                continue;
            }
            
            // Try to decode as JSON
            $decoded = json_decode($fotoDokumentasi, true);
            
            // If JSON decode failed or result is not array, fix it
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                // If it's a string that's not JSON, convert to array
                if (is_string($fotoDokumentasi)) {
                    $fixed = [$fotoDokumentasi];
                } else {
                    // If it's some other type, make it empty array
                    $fixed = [];
                }
                
                // Update with proper JSON
                DB::table('tracking_history')
                    ->where('id', $history->id)
                    ->update(['foto_dokumentasi' => json_encode($fixed)]);
                    
                echo "Fixed foto_dokumentasi for tracking_history ID: {$history->id}\n";
            }
        }
        
        echo "foto_dokumentasi field cleanup completed.\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this cleanup
    }
};
