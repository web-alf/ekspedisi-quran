<?php

namespace App\Console\Commands;

use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\WakafBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CreateMissingWakafBatches extends Command
{
    protected $signature = 'wakaf:create-missing-batches {--fix-certificates : Also fix certificate data synchronization}';

    protected $description = 'Create missing WakafBatch records for existing donatur with wakaf items and fix certificate data sync';

    public function handle()
    {
        $this->info('Starting to create missing WakafBatch records...');

        DB::beginTransaction();

        try {
            // Get all donatur that have wakaf items but no wakaf batches
            $donatursWithItems = Donatur::whereHas('wakafItems')
                ->whereDoesntHave('wakafBatches')
                ->get();

            if ($donatursWithItems->isEmpty()) {
                $this->info('No donatur found with missing WakafBatch records.');
                DB::commit();

                return Command::SUCCESS;
            }

            $this->info("Found {$donatursWithItems->count()} donatur with missing WakafBatch records.");

            $batchesCreated = 0;

            foreach ($donatursWithItems as $donatur) {
                $this->info("Processing donatur: {$donatur->nama_donatur} (ID: {$donatur->id})");

                // Get all wakaf items for this donatur (consolidated approach)
                $allWakifItems = $donatur->wakafItems()
                    ->whereNotNull('wakif_name')
                    ->where('wakif_name', '!=', '')
                    ->get();

                $totalItems = $allWakifItems->count();

                if ($totalItems == 0) {
                    $this->warn("  No wakif items found for donatur: {$donatur->nama_donatur}");
                    continue;
                }

                // Get unique wakif names for logging
                $uniqueWakifNames = $allWakifItems->pluck('wakif_name')->unique();

                // Use A5 as default jenis_quran for consolidated batches
                $jenisQuran = JenisQuran::where('kode_jenis', 'A5')->first();

                if (! $jenisQuran) {
                    $this->warn("JenisQuran not found for type: A5");
                    continue;
                }

                // Generate unique batch code
                $lastBatch = WakafBatch::orderBy('id', 'desc')->first();
                $nextNumber = $lastBatch ? $lastBatch->id + 1 : 1;
                $batchCode = 'WB-'.date('Y').'-'.str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

                // Create ONE consolidated WakafBatch per donatur
                $wakafBatch = WakafBatch::create([
                    'batch_code' => $batchCode,
                    'donatur_id' => $donatur->id,
                    'jenis_quran_id' => $jenisQuran->id,
                    'total_quran' => $totalItems,
                    'tanggal_wakaf' => $donatur->donation_date ?? now(),
                    'status' => 'ready',
                    'created_by' => 1, // System user
                    'catatan' => "Consolidated batch for wakif names: " . $uniqueWakifNames->implode(', '),
                ]);

                // Update wakaf items with the batch ID (if wakaf_items has wakaf_batch_id column)
                // All wakif items belong to this single consolidated batch
                if (Schema::hasColumn('wakaf_items', 'wakaf_batch_id')) {
                    $donatur->wakafItems()
                        ->whereNotNull('wakif_name')
                        ->where('wakif_name', '!=', '')
                        ->whereNull('wakaf_batch_id')
                        ->update(['wakaf_batch_id' => $wakafBatch->id]);
                }

                $this->info("  Created consolidated batch: {$batchCode} with {$totalItems} items for wakif names: " . $uniqueWakifNames->implode(', '));
                $batchesCreated++;
            }

            DB::commit();

            $this->info("✅ Successfully created {$batchesCreated} WakafBatch records!");

            // Fix certificate data synchronization if requested
            if ($this->option('fix-certificates')) {
                $this->info("\n=== FIXING CERTIFICATE DATA SYNCHRONIZATION ===");
                $this->fixCertificateDataSync();
            }

            // Show summary
            $totalBatches = WakafBatch::count();
            $this->info("Total WakafBatch records in database: {$totalBatches}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed to create WakafBatch records: '.$e->getMessage());
            Log::error('CreateMissingWakafBatches failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Fix certificate data synchronization issues
     */
    private function fixCertificateDataSync()
    {
        // 1. Update donation_count based on unique wakif names
        $donatursWithWakifItems = Donatur::whereHas('wakafItems', function ($query) {
            $query->whereNotNull('wakif_name')->where('wakif_name', '!=', '');
        })->with(['wakafItems' => function ($query) {
            $query->whereNotNull('wakif_name')->where('wakif_name', '!=', '');
        }])->get();

        $this->info('Updating donation_count for donatur with multiple wakif names...');

        $updatedCount = 0;
        foreach ($donatursWithWakifItems as $donatur) {
            $uniqueWakifCount = $donatur->wakafItems->pluck('wakif_name')->unique()->count();
            $currentDonationCount = $donatur->donation_count ?? 1;

            if ($currentDonationCount != $uniqueWakifCount) {
                $donatur->update(['donation_count' => $uniqueWakifCount]);
                $this->info("  Updated {$donatur->nama_donatur}: {$currentDonationCount} → {$uniqueWakifCount} donations");
                $updatedCount++;
            }
        }

        $this->info("✅ Updated donation_count for {$updatedCount} donatur");

        // 2. Consolidate existing separate batches into single batches
        $this->info("\nConsolidating separate batches into single consolidated batches...");

        $consolidatedCount = 0;
        foreach ($donatursWithWakifItems as $donatur) {
            $existingBatches = $donatur->wakafBatches()->get();

            // If donatur has multiple batches, consolidate them
            if ($existingBatches->count() > 1) {
                $this->info("  Consolidating {$existingBatches->count()} batches for {$donatur->nama_donatur}");

                // Calculate total mushaf from all existing batches
                $totalMushaf = $existingBatches->sum('total_quran');

                // Get all wakif items
                $allWakifItems = $donatur->wakafItems()
                    ->whereNotNull('wakif_name')
                    ->where('wakif_name', '!=', '')
                    ->get();

                $uniqueWakifNames = $allWakifItems->pluck('wakif_name')->unique();

                // Delete existing separate batches (and their certificates)
                foreach ($existingBatches as $batch) {
                    // Delete any associated certificates first
                    $batch->sertifikat()->delete();
                    $batch->delete();
                }

                // Create one consolidated batch
                $jenisQuran = JenisQuran::where('kode_jenis', 'A5')->first();
                $lastBatch = WakafBatch::orderBy('id', 'desc')->first();
                $nextNumber = $lastBatch ? $lastBatch->id + 1 : 1;
                $batchCode = 'WB-'.date('Y').'-'.str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

                $consolidatedBatch = WakafBatch::create([
                    'batch_code' => $batchCode,
                    'donatur_id' => $donatur->id,
                    'jenis_quran_id' => $jenisQuran->id,
                    'total_quran' => $totalMushaf,
                    'tanggal_wakaf' => $donatur->donation_date ?? now(),
                    'status' => 'pending_distribution',
                    'created_by' => 1,
                    'catatan' => "Consolidated batch for wakif names: " . $uniqueWakifNames->implode(', '),
                ]);

                // Update wakaf items to point to the new consolidated batch
                if (Schema::hasColumn('wakaf_items', 'wakaf_batch_id')) {
                    $donatur->wakafItems()
                        ->whereNotNull('wakif_name')
                        ->where('wakif_name', '!=', '')
                        ->update(['wakaf_batch_id' => $consolidatedBatch->id]);
                }

                $this->info("    Consolidated into batch: {$batchCode} with {$totalMushaf} items for wakif names: " . $uniqueWakifNames->implode(', '));
                $consolidatedCount++;
            }
        }

        $this->info("✅ Consolidated {$consolidatedCount} donatur from multiple batches to single consolidated batches");
    }
}
