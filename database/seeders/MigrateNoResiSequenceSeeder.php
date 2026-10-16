<?php

namespace Database\Seeders;

use App\Models\NoResiSequence;
use App\Models\Pengiriman;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateNoResiSequenceSeeder extends Seeder
{
    /**
     * Seed no_resi_sequences table based on existing pengiriman data
     *
     * Run this ONCE after migration untuk sync existing data
     */
    public function run(): void
    {
        $this->command->info('🔄 Migrating existing no_resi to sequence table...');

        // Get all unique years dari existing pengiriman
        $years = Pengiriman::selectRaw('DISTINCT YEAR(created_at) as year')
            ->whereNotNull('no_resi')
            ->pluck('year')
            ->toArray();

        if (empty($years)) {
            $this->command->warn('⚠️  No existing pengiriman found. Creating sequence for current year only.');
            $years = [(int) date('Y')];
        }

        foreach ($years as $year) {
            // Get highest number untuk tahun ini
            $highestNumber = $this->getHighestNumberForYear($year);

            // Update or create sequence
            NoResiSequence::updateOrCreate(
                ['year' => $year],
                ['last_number' => $highestNumber]
            );

            $this->command->info("✅ Year {$year}: Set sequence to {$highestNumber}");
        }

        // Ensure current year exists
        $currentYear = (int) date('Y');
        if (!in_array($currentYear, $years)) {
            NoResiSequence::updateOrCreate(
                ['year' => $currentYear],
                ['last_number' => 0]
            );
            $this->command->info("✅ Year {$currentYear}: Created with sequence 0");
        }

        $this->command->info('✨ Migration completed successfully!');
    }

    /**
     * Get highest number dari no_resi untuk tahun tertentu
     *
     * @param int $year
     * @return int
     */
    private function getHighestNumberForYear(int $year): int
    {
        $prefix = "EQ-{$year}-";

        $lastResi = Pengiriman::where('no_resi', 'LIKE', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING(no_resi, -5) AS UNSIGNED) DESC')
            ->first();

        if (!$lastResi) {
            return 0;
        }

        // Extract last 5 digits
        $lastNumber = (int) substr($lastResi->no_resi, -5);

        return $lastNumber;
    }
}
