<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaUploadService
{
    /**
     * Upload media to temporary public service for external APIs
     */
    public function uploadToPublicUrl($filePath, $originalName)
    {
        // Check if APP_URL is localhost
        $appUrl = config('app.url');
        
        if (str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
            // Option 1: Try to use ngrok URL if available
            $ngrokUrl = $this->detectNgrokUrl();
            if ($ngrokUrl) {
                return $ngrokUrl . '/storage/' . $filePath;
            }
            
            // Option 2: Upload to temporary file sharing service
            return $this->uploadToTempService($filePath, $originalName);
        }
        
        // For production, use normal asset URL
        return asset('storage/' . $filePath);
    }
    
    /**
     * Detect ngrok URL from environment or ngrok API
     */
    private function detectNgrokUrl()
    {
        // Check if NGROK_URL is set in environment
        $ngrokUrl = config('services.ngrok.url');
        if ($ngrokUrl) {
            return rtrim($ngrokUrl, '/');
        }
        
        // Try to detect ngrok from API
        try {
            $response = Http::timeout(2)->get('http://127.0.0.1:4040/api/tunnels');
            
            if ($response->successful()) {
                $data = $response->json();
                
                foreach ($data['tunnels'] ?? [] as $tunnel) {
                    if (isset($tunnel['public_url']) && str_contains($tunnel['public_url'], 'https://')) {
                        // Check if it's for port 8000 (Laravel default)
                        if (str_contains($tunnel['config']['addr'] ?? '', '8000')) {
                            Log::info('Auto-detected ngrok URL', ['url' => $tunnel['public_url']]);
                            return $tunnel['public_url'];
                        }
                    }
                }
                
                // If no port 8000 tunnel, return the first https tunnel
                foreach ($data['tunnels'] ?? [] as $tunnel) {
                    if (isset($tunnel['public_url']) && str_contains($tunnel['public_url'], 'https://')) {
                        Log::info('Auto-detected ngrok URL (fallback)', ['url' => $tunnel['public_url']]);
                        return $tunnel['public_url'];
                    }
                }
            }
        } catch (\Exception $e) {
            // Ngrok API not accessible, that's okay
        }
        
        return null;
    }
    
    /**
     * Upload to temporary file sharing service
     */
    private function uploadToTempService($filePath, $originalName)
    {
        try {
            $fullPath = storage_path('app/public/' . $filePath);
            
            if (!file_exists($fullPath)) {
                throw new \Exception("File not found: {$fullPath}");
            }
            
            // Option 1: Upload to 0x0.st (temporary file host)
            $response = Http::timeout(30)
                ->attach('file', file_get_contents($fullPath), $originalName)
                ->post('https://0x0.st');
            
            if ($response->successful()) {
                $tempUrl = trim($response->body());
                Log::info('Media uploaded to temporary service', [
                    'local_path' => $filePath,
                    'temp_url' => $tempUrl
                ]);
                return $tempUrl;
            }
            
            // Option 2: Upload to file.io (alternative)
            $response = Http::timeout(30)
                ->attach('file', file_get_contents($fullPath), $originalName)
                ->post('https://file.io');
            
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['link'])) {
                    Log::info('Media uploaded to file.io', [
                        'local_path' => $filePath,
                        'temp_url' => $data['link']
                    ]);
                    return $data['link'];
                }
            }
            
            // Fallback: Return warning URL
            Log::warning('Failed to upload to temporary service', [
                'file' => $filePath
            ]);
            
            return 'https://example.com/media-upload-failed.jpg';
            
        } catch (\Exception $e) {
            Log::error('Media upload service error', [
                'error' => $e->getMessage(),
                'file' => $filePath
            ]);
            
            return 'https://example.com/media-upload-error.jpg';
        }
    }
    
    /**
     * Get media URL that's accessible by external APIs
     */
    public function getPublicMediaUrl($storagePath, $originalName)
    {
        return $this->uploadToPublicUrl($storagePath, $originalName);
    }
}