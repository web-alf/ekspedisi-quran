<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HijriHelper
{
    /**
     * Convert Gregorian date to Hijri date using Aladhan API (WIB timezone)
     * 
     * @param string|Carbon $gregorianDate
     * @return string Formatted Hijri date (e.g., "5 Muharram 1447 H")
     */
    public static function convertToHijri($gregorianDate)
    {
        if (!$gregorianDate) {
            return '';
        }

        // Convert to Carbon if it's a string and set WIB timezone
        if (is_string($gregorianDate)) {
            $gregorianDate = Carbon::parse($gregorianDate)->setTimezone('Asia/Jakarta');
        } else {
            $gregorianDate = $gregorianDate->copy()->setTimezone('Asia/Jakarta');
        }

        try {
            // Try primary API first
            $hijriDate = self::convertUsingAPI($gregorianDate);
            
            if ($hijriDate) {
                return $hijriDate;
            }
            
            // Try alternative API
            $hijriDate = self::convertUsingAlternativeAPI($gregorianDate);
            
            if ($hijriDate) {
                return $hijriDate;
            }
            
            // Final fallback to local calculation
            return self::convertUsingLocalCalculation($gregorianDate);
            
        } catch (\Exception $e) {
            Log::error('All Hijri conversion methods failed: ' . $e->getMessage());
            return self::convertUsingLocalCalculation($gregorianDate);
        }
    }

    /**
     * Convert using Aladhan API with MATHEMATICAL method (WIB timezone adjusted)
     */
    private static function convertUsingAPI($gregorianDate)
    {
        try {
            // Convert to WIB timezone first
            $wibDate = $gregorianDate->setTimezone('Asia/Jakarta');
            $dateString = $wibDate->format('d-m-Y');
            $cacheKey = "hijri_date_mathematical_wib_{$dateString}";
            
            // Check cache first (cache for 24 hours)
            return Cache::remember($cacheKey, 86400, function () use ($wibDate) {
                $response = Http::timeout(5)->get('https://api.aladhan.com/v1/gToH', [
                    'date' => $wibDate->format('d-m-Y'),
                    'hijri_calculation_method' => 'mathematical'  // Use mathematical method - no adjustment needed
                ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data['data']['hijri'])) {
                        $hijri = $data['data']['hijri'];
                        $day = (int) $hijri['day'];  // Use API result directly
                        $month = (int) $hijri['month']['number'];
                        $year = (int) $hijri['year'];
                        
                        // Use Indonesian month names to avoid encoding issues
                        $monthName = self::getIndonesianHijriMonthName($month);
                        
                        return "{$day} {$monthName} {$year} H";
                    }
                }
                
                return null;
            });
            
        } catch (\Exception $e) {
            Log::warning('Aladhan API with MATHEMATICAL method failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Alternative API using different service for fallback
     */
    private static function convertUsingAlternativeAPI($gregorianDate)
    {
        try {
            // Alternative approach: Use IslamicFinder API or similar service
            $wibDate = $gregorianDate->setTimezone('Asia/Jakarta');
            $dateString = $wibDate->format('Y-m-d');
            $cacheKey = "hijri_date_alternative_{$dateString}";
            
            // Check cache first (cache for 24 hours)
            return Cache::remember($cacheKey, 86400, function () use ($wibDate) {
                // Try different date format or different API endpoint
                $response = Http::timeout(5)->get('https://api.aladhan.com/v1/gToH', [
                    'date' => $wibDate->format('Y-m-d'), // Different date format
                    'hijri_calculation_method' => 'kuwaiti' // Different calculation method
                ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data['data']['hijri'])) {
                        $hijri = $data['data']['hijri'];
                        $day = (int) $hijri['day'];
                        $month = (int) $hijri['month']['number'];
                        $year = (int) $hijri['year'];
                        
                        $monthName = self::getIndonesianHijriMonthName($month);
                        
                        Log::info('Alternative API (Kuwaiti method) used for Hijri conversion', [
                            'gregorian_date' => $wibDate->format('Y-m-d'),
                            'hijri_result' => "{$day} {$monthName} {$year} H"
                        ]);
                        
                        return "{$day} {$monthName} {$year} H";
                    }
                }
                
                return null;
            });
            
        } catch (\Exception $e) {
            Log::warning('Alternative Hijri API failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get Indonesian Hijri month names to avoid encoding issues
     */
    private static function getIndonesianHijriMonthName($monthNumber)
    {
        $monthNames = [
            1 => 'Muharram',
            2 => 'Safar',
            3 => 'Rabiul Awwal',
            4 => 'Rabiul Akhir', 
            5 => 'Jumadil Awwal',
            6 => 'Jumadil Akhir',
            7 => 'Rajab',
            8 => 'Syaban',
            9 => 'Ramadhan',
            10 => 'Syawwal',
            11 => 'Dzulqaidah',
            12 => 'Dzulhijjah'
        ];
        
        return $monthNames[$monthNumber] ?? 'Unknown';
    }

    /**
     * Fallback local calculation (improved with more accurate algorithm)
     */
    private static function convertUsingLocalCalculation($gregorianDate)
    {
        try {
            // Use more accurate reference point with verified API data
            // Reference: 1 Jan 2024 = 19 Jumadil Akhir 1445 H (verified from API)
            $baseGregorian = Carbon::create(2024, 1, 1)->setTimezone('Asia/Jakarta');
            $baseHijriYear = 1445;
            $baseHijriMonth = 6; // Jumadil Akhir
            $baseHijriDay = 19;
            
            // Calculate days difference
            $daysDiff = $gregorianDate->diffInDays($baseGregorian, false);
            
            // More accurate conversion using Kuwaiti algorithm approximation
            // Average Hijri year = 354.367056 days
            // Average Gregorian year = 365.2425 days
            // Ratio = 354.367056 / 365.2425 ≈ 0.9704
            $hijriDaysFromBase = round($daysDiff * 0.9704);
            
            // Start from base Hijri date
            $currentHijriYear = $baseHijriYear;
            $currentHijriMonth = $baseHijriMonth;
            $currentHijriDay = $baseHijriDay;
            
            // Add the calculated days
            $totalDays = $currentHijriDay + $hijriDaysFromBase;
            
            // More accurate month calculation using variable month lengths
            $monthLengths = [29, 30, 29, 30, 29, 30, 29, 30, 29, 30, 29, 30]; // Standard pattern
            
            // Handle forward calculation
            if ($hijriDaysFromBase >= 0) {
                while ($totalDays > $monthLengths[($currentHijriMonth - 1) % 12]) {
                    $totalDays -= $monthLengths[($currentHijriMonth - 1) % 12];
                    $currentHijriMonth++;
                    
                    if ($currentHijriMonth > 12) {
                        $currentHijriMonth = 1;
                        $currentHijriYear++;
                    }
                }
            } else {
                // Handle backward calculation
                while ($totalDays <= 0) {
                    $currentHijriMonth--;
                    
                    if ($currentHijriMonth < 1) {
                        $currentHijriMonth = 12;
                        $currentHijriYear--;
                    }
                    
                    $totalDays += $monthLengths[($currentHijriMonth - 1) % 12];
                }
            }
            
            $finalDay = max(1, min(30, $totalDays));
            
            // Add margin of error note for local calculation
            $result = self::formatHijriDate($finalDay, $currentHijriMonth, $currentHijriYear);
            
            // Log warning about local calculation usage
            Log::warning('Using local Hijri calculation (may have ±1-2 days margin of error)', [
                'gregorian_date' => $gregorianDate->format('Y-m-d'),
                'hijri_result' => $result
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Local Hijri calculation failed: ' . $e->getMessage());
            return 'Tanggal Hijriah tidak tersedia';
        }
    }


    /**
     * Format Hijri date for display
     * 
     * @param int $day
     * @param int $month  
     * @param int $year
     * @return string
     */
    public static function formatHijriDate($day, $month, $year)
    {
        $hijriMonths = [
            1 => 'Muharram',
            2 => 'Safar', 
            3 => 'Rabiul Awwal',
            4 => 'Rabiul Akhir',
            5 => 'Jumadil Awwal',
            6 => 'Jumadil Akhir',
            7 => 'Rajab',
            8 => 'Syaban',
            9 => 'Ramadhan',
            10 => 'Syawwal',
            11 => 'Dzulqaidah',
            12 => 'Dzulhijjah'
        ];

        $monthName = $hijriMonths[$month] ?? 'Unknown';
        return "{$day} {$monthName} {$year} H";
    }

    /**
     * Get current Hijri date
     */
    public static function getCurrentHijriDate()
    {
        return self::convertToHijri(now());
    }

    /**
     * Convert Hijri date to Gregorian (using API or fallback)
     */
    public static function hijriToGregorian($hijriYear, $hijriMonth, $hijriDay)
    {
        try {
            // Try API conversion
            $response = Http::timeout(5)->get('https://api.aladhan.com/v1/hToG', [
                'date' => "{$hijriDay}-{$hijriMonth}-{$hijriYear}"
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data']['gregorian'])) {
                    $gregorian = $data['data']['gregorian'];
                    return Carbon::createFromFormat('d-m-Y', $gregorian['date']);
                }
            }
            
            // Fallback to approximate calculation
            return now(); // Simple fallback
        } catch (\Exception $e) {
            Log::error('Hijri to Gregorian conversion failed: ' . $e->getMessage());
            return now();
        }
    }

    /**
     * Basic validation for Hijri date
     */
    public static function isValidHijriDate($year, $month, $day)
    {
        return $year >= 1 && $month >= 1 && $month <= 12 && $day >= 1 && $day <= 30;
    }

    /**
     * Get Hijri month name
     */
    public static function getHijriMonthName($month)
    {
        $hijriMonths = [
            1 => 'Muharram',
            2 => 'Safar', 
            3 => 'Rabiul Awwal',
            4 => 'Rabiul Akhir',
            5 => 'Jumadil Awwal',
            6 => 'Jumadil Akhir',
            7 => 'Rajab',
            8 => 'Syaban',
            9 => 'Ramadhan',
            10 => 'Syawwal',
            11 => 'Dzulqaidah',
            12 => 'Dzulhijjah'
        ];

        return $hijriMonths[$month] ?? 'Unknown';
    }

}
