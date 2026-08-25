<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Setting;

class CleanupJsonContentSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'content:cleanup-json {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old JSON content settings after migrating to database tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for old JSON content settings...');
        
        $jsonSettings = [
            'landing_testimonials',
            'landing_gallery', 
            'landing_faqs'
        ];
        
        $settings = Setting::whereIn('key', $jsonSettings)->get();
        
        if ($settings->isEmpty()) {
            $this->info('No JSON content settings found. Already cleaned up!');
            return Command::SUCCESS;
        }
        
        $this->table(
            ['Key', 'Type', 'Size (bytes)'],
            $settings->map(function ($setting) {
                return [
                    $setting->key,
                    $setting->type,
                    strlen($setting->value)
                ];
            })
        );
        
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN: The above settings would be deleted.');
            return Command::SUCCESS;
        }
        
        if (!$this->confirm('Do you want to delete these JSON settings? This action cannot be undone.')) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }
        
        // Create backup before deletion
        $backup = [];
        foreach ($settings as $setting) {
            $backup[$setting->key] = [
                'value' => $setting->value,
                'type' => $setting->type,
                'deleted_at' => now()->toIso8601String()
            ];
        }
        
        $backupPath = storage_path('app/backups/json-content-settings-' . now()->format('Y-m-d-His') . '.json');
        
        // Ensure backup directory exists
        if (!file_exists(dirname($backupPath))) {
            mkdir(dirname($backupPath), 0755, true);
        }
        
        file_put_contents($backupPath, json_encode($backup, JSON_PRETTY_PRINT));
        $this->info('Backup created at: ' . $backupPath);
        
        // Delete the settings
        $deleted = Setting::whereIn('key', $jsonSettings)->delete();
        
        $this->info("Successfully deleted {$deleted} JSON content settings.");
        $this->info('Content is now managed through the admin panel under "Konten Landing".');
        
        return Command::SUCCESS;
    }
}