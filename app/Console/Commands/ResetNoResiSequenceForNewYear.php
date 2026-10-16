<?php

namespace App\Console\Commands;

use App\Models\NoResiSequence;
use Illuminate\Console\Command;

class ResetNoResiSequenceForNewYear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noresi:reset-year {year?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset no_resi sequence untuk tahun baru (otomatis di 1 Januari)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $year = $this->argument('year') ?? (int) date('Y');

        if (!is_numeric($year) || $year < 2020 || $year > 2100) {
            $this->error('❌ Invalid year! Year must be between 2020-2100');
            return 1;
        }

        $year = (int) $year;

        // Check if sequence already exists
        $existing = NoResiSequence::where('year', $year)->first();

        if ($existing) {
            if (!$this->confirm("⚠️  Sequence untuk tahun {$year} sudah ada dengan last_number: {$existing->last_number}. Reset ke 0?")) {
                $this->info('❌ Operasi dibatalkan.');
                return 0;
            }
        }

        // Reset or create sequence
        NoResiSequence::resetForNewYear($year);

        $this->info("✅ Sequence untuk tahun {$year} berhasil direset ke 0");
        $this->line("   No resi selanjutnya: EQ-{$year}-00001");

        return 0;
    }
}
