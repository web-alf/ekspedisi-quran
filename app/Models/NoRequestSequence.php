<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NoRequestSequence extends Model
{
    protected $fillable = [
        'year',
        'last_number',
    ];

    /**
     * Ambil nomor berikutnya secara atomic dan race-condition safe.
     *
     * @param int $year Tahun untuk sequence
     * @return int Nomor berikutnya
     */
    public static function getNextNumber(int $year): int
    {
        $maxAttempts = 5;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                return DB::transaction(function () use ($year) {
                    // Ambil dan kunci baris sequence milik tahun ini
                    $sequence = static::where('year', $year)
                        ->lockForUpdate()
                        ->first();

                    if (! $sequence) {
                        $sequence = static::create([
                            'year' => $year,
                            'last_number' => 0,
                        ]);
                    }

                    $nextNumber = $sequence->last_number + 1;

                    // REQ-YYYY-XXXXX hanya support 5 digit (max 99999)
                    if ($nextNumber > 99999) {
                        throw new \Exception(
                            "Sequence limit reached for year {$year}. ".
                            "Maximum 99,999 mushaf requests per year. ".
                            "Current: {$sequence->last_number}"
                        );
                    }

                    $sequence->increment('last_number');

                    return $nextNumber;
                });
            } catch (\Illuminate\Database\QueryException $e) {
                // Deadlock (40001 / 1213): retry dengan exponential backoff
                if (in_array($e->getCode(), ['40001', 1213]) && $attempt < $maxAttempts - 1) {
                    $attempt++;
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
}
