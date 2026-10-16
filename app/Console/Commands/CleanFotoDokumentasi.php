<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\TrackingHistory;

class CleanFotoDokumentasi extends Command
{
    protected $signature = 'db:clean-foto-dokumentasi';
    protected $description = 'Clean and fix corrupt foto_dokumentasi data in tracking_history table';

    public function handle()
    {
        $this->info('Starting foto_dokumentasi cleanup...');
        
        $fixed = 0;
        $skipped = 0;
        
        // Get all tracking history records
        TrackingHistory::chunk(100, function($histories) use (&$fixed, &$skipped) {
            foreach ($histories as $history) {
                try {
                    // Try to access foto_dokumentasi to trigger any errors
                    $fotoDokumentasi = $history->foto_dokumentasi;
                    
                    // Get raw data from database
                    $rawData = DB::table('tracking_history')
                        ->where('id', $history->id)
                        ->value('foto_dokumentasi');
                    
                    if ($rawData === null) {
                        $skipped++;
                        continue;
                    }
                    
                    // Test if it's valid JSON array
                    $decoded = json_decode($rawData, true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // Invalid JSON, fix it
                        $this->warn("Fixing invalid JSON for ID {$history->id}: {$rawData}");
                        
                        // Convert to array format
                        if (is_string($rawData) && !empty($rawData)) {
                            $fixedData = [$rawData];
                        } else {
                            $fixedData = [];
                        }
                        
                        // Update with proper JSON
                        DB::table('tracking_history')
                            ->where('id', $history->id)
                            ->update(['foto_dokumentasi' => json_encode($fixedData)]);
                            
                        $fixed++;
                        $this->line("  → Fixed to: " . json_encode($fixedData));
                    } else if (!is_array($decoded)) {
                        // Valid JSON but not array, fix it
                        $this->warn("Converting non-array JSON for ID {$history->id}");
                        
                        $fixedData = is_string($decoded) ? [$decoded] : [];
                        
                        DB::table('tracking_history')
                            ->where('id', $history->id)
                            ->update(['foto_dokumentasi' => json_encode($fixedData)]);
                            
                        $fixed++;
                    } else {
                        $skipped++;
                    }
                    
                } catch (\Exception $e) {
                    $this->error("Error processing ID {$history->id}: " . $e->getMessage());
                    
                    // Force fix by setting to empty array
                    DB::table('tracking_history')
                        ->where('id', $history->id)
                        ->update(['foto_dokumentasi' => json_encode([])]);
                        
                    $fixed++;
                }
            }
        });
        
        $this->info("Cleanup completed!");
        $this->line("Fixed: {$fixed} records");
        $this->line("Skipped: {$skipped} records");
        
        return 0;
    }
}
