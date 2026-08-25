<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\StatusPengiriman;

class StatusPengirimanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Remove old inconsistent statuses first
        StatusPengiriman::whereIn('slug', ['pending', 'dikemas', 'dikirim'])
            ->whereNotIn('slug', ['diterima', 'batal']) // Keep diterima and batal
            ->delete();
            
        // Use the standardized statuses from the model
        $statusPengiriman = StatusPengiriman::getDefaultStatuses();

        // Use updateOrCreate to avoid duplicates
        foreach ($statusPengiriman as $status) {
            StatusPengiriman::updateOrCreate(
                ['slug' => $status['slug']], // Find by slug
                $status // Update or create with this data
            );
        }
        
        if ($this->command) {
            $this->command->info('✅ StatusPengiriman seeded with standardized statuses!');
        }
    }
}
