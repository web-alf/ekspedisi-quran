<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CopyCertificateFiles extends Command
{
    protected $signature = 'certificate:copy-files';
    protected $description = 'Copy certificate files from private to public location';

    public function handle()
    {
        $this->info('🚀 Copying certificate files...');
        
        $sourceDir = storage_path('app/private/certificates');
        $targetDir = storage_path('app/certificates');
        
        if (!File::exists($sourceDir)) {
            $this->error('Source directory does not exist: ' . $sourceDir);
            return 1;
        }
        
        // Create target directory
        File::makeDirectory($targetDir, 0755, true, true);
        
        // Copy all files recursively
        $files = File::allFiles($sourceDir);
        $copied = 0;
        
        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();
            $targetPath = $targetDir . '/' . $relativePath;
            $targetDirPath = dirname($targetPath);
            
            // Create target directory if it doesn't exist
            if (!File::exists($targetDirPath)) {
                File::makeDirectory($targetDirPath, 0755, true);
            }
            
            // Copy file if it doesn't exist
            if (!File::exists($targetPath)) {
                File::copy($file->getPathname(), $targetPath);
                $this->info("✅ Copied: {$relativePath}");
                $copied++;
            } else {
                $this->line("⏭️  Skipped (exists): {$relativePath}");
            }
        }
        
        $this->info("🎉 Copy completed! {$copied} files copied.");
        return 0;
    }
}
