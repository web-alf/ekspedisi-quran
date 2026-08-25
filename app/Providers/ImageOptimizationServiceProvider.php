<?php

namespace App\Providers;

use App\Services\ImageOptimizationService;
use App\Console\Commands\OptimizeImages;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;

class ImageOptimizationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../../config/image_optimization.php', 'image_optimization'
        );

        // Register service
        $this->app->singleton(ImageOptimizationService::class, function ($app) {
            return new ImageOptimizationService();
        });

        // Register alias
        $this->app->alias(ImageOptimizationService::class, 'image.optimizer');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../../config/image_optimization.php' => config_path('image_optimization.php'),
        ], 'image-optimization-config');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                OptimizeImages::class,
            ]);
        }

        // Create required directories
        $this->createRequiredDirectories();
    }

    /**
     * Create required directories for thumbnails
     */
    protected function createRequiredDirectories(): void
    {
        $directories = [
            'galleries',
            'testimonials', 
            'uploads',
            'settings',
            'certificates',
            'avatars',
            'logos'
        ];
        
        $storagePath = storage_path('app/public');

        foreach ($directories as $directory) {
            $fullPath = $storagePath . '/' . $directory;
            $thumbnailPath = $fullPath . '/thumbnails';

            if (!File::exists($fullPath)) {
                File::makeDirectory($fullPath, 0755, true);
            }

            if (!File::exists($thumbnailPath)) {
                File::makeDirectory($thumbnailPath, 0755, true);
            }
        }

        // Create backup directory if enabled
        if (config('image_optimization.backup.enabled', false)) {
            $backupPath = $storagePath . '/' . config('image_optimization.backup.directory', 'backups/images');
            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0755, true);
            }
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            ImageOptimizationService::class,
            'image.optimizer',
        ];
    }
}
