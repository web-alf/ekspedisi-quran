<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NoResiSequence extends Model
{
    protected $fillable = [
        'year',
        'last_number',
    ];

    /**
     * Get next number untuk nomor resi dengan atomic increment
     * Race-condition safe menggunakan database lock
     *
     * @param int $year Tahun untuk sequence
     * @return int Next number
     */
    public static function getNextNumber(int $year): int
    {
        $maxAttempts = 5;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                return DB::transaction(function () use ($year) {
                    // Get and lock row untuk update atomic
                    $sequence = static::where('year', $year)
                        ->lockForUpdate()
                        ->first();

                    // Jika belum ada sequence untuk tahun ini, buat baru
                    if (!$sequence) {
                        $sequence = static::create([
                            'year' => $year,
                            'last_number' => 0,
                        ]);
                    }

                    // Increment number secara atomic
                    $nextNumber = $sequence->last_number + 1;

                    // ✅ Check limit: EQ-YYYY-XXXXX hanya support 5 digits (max 99999)
                    if ($nextNumber > 99999) {
                        throw new \Exception(
                            "Sequence limit reached for year {$year}. ".
                            "Maximum 99,999 pengiriman per year. ".
                            "Current: {$sequence->last_number}"
                        );
                    }

                    // Update dengan increment atomic
                    $sequence->increment('last_number');

                    return $nextNumber;
                });
            } catch (\Illuminate\Database\QueryException $e) {
                // Check if deadlock (error code 40001 or 1213)
                if (in_array($e->getCode(), ['40001', 1213]) && $attempt < $maxAttempts - 1) {
                    $attempt++;
                    // Exponential backoff: 50-150ms, then 100-300ms, etc.
                    $minDelay = 50000 * $attempt; // microseconds
                    $maxDelay = 150000 * $attempt;
                    usleep(random_int($minDelay, $maxDelay));
                    continue;
                }
                throw $e;
            }
        }

        throw new \Exception("Failed to get sequence after {$maxAttempts} attempts");
    }

    /**
     * Get current last number untuk tahun tertentu (tanpa increment)
     *
     * @param int $year
     * @return int
     */
    public static function getCurrentNumber(int $year): int
    {
        $sequence = static::where('year', $year)->first();

        return $sequence ? $sequence->last_number : 0;
    }

    /**
     * Reset sequence untuk tahun baru (untuk cron job tahunan)
     *
     * @param int $year
     * @return void
     */
    public static function resetForNewYear(int $year): void
    {
        static::updateOrCreate(
            ['year' => $year],
            ['last_number' => 0]
        );
    }
}
