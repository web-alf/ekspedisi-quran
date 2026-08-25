<?php

namespace App\Helpers;

/**
 * MediaHelper - Utilities for handling media files
 * 
 * This helper provides methods for generating public URLs that can be accessed
 * by external services.
 */
class MediaHelper
{
    /**
     * Generate public URL for media files accessible by external services
     * 
     * @param string $filePath The file path relative to public directory
     * @return string The complete public URL
     */
    public static function getPublicMediaUrl($filePath)
    {
        // Normalize file path
        $filePath = ltrim($filePath, '/');
        
        // Get base URL based on environment
        $baseUrl = self::getBaseUrl();
        
        return rtrim($baseUrl, '/') . '/' . $filePath;
    }
    
    /**
     * Get the appropriate base URL for the current environment
     * 
     * @return string The base URL to use
     */
    protected static function getBaseUrl()
    {
        // Always use app URL as fallback
        return config('app.url');
    }
    
    /**
     * Upload media file and return public URL
     */
    public static function uploadAndGetUrl($file, $directory = 'media')
    {
        // Store file in public disk
        $path = $file->store($directory, 'public');
        
        // Return public URL
        return self::getPublicMediaUrl('storage/' . $path);
    }
    
    /**
     * Check if media URL is accessible
     */
    public static function isUrlAccessible($url)
    {
        try {
            $headers = get_headers($url, 1);
            return isset($headers[0]) && strpos($headers[0], '200') !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
}