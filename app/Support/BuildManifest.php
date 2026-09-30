<?php

namespace App\Support;

/**
 * Reads Vite's build manifest and reports the frontend sizes that the
 * performance budgets actually care about:
 *
 * - the largest single chunk a browser has to download and parse
 * - the total of the entry bundles loaded on first paint
 *
 * Summing every file in the manifest (the old behaviour) measures the size of
 * the whole build, which is not what a per-bundle budget means.
 */
class BuildManifest
{
    /**
     * @return array{largest_chunk_kb: float, largest_chunk: string, entry_total_kb: float}
     */
    public static function frontendSizes(string $manifestPath): array
    {
        $empty = ['largest_chunk_kb' => 0.0, 'largest_chunk' => '', 'entry_total_kb' => 0.0];

        if (! is_file($manifestPath)) {
            return $empty;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest)) {
            return $empty;
        }

        $baseDirectory = dirname($manifestPath);
        $largestChunkKb = 0.0;
        $largestChunk = '';
        $entryTotalKb = 0.0;

        foreach ($manifest as $details) {
            if (! is_array($details) || ! isset($details['file'])) {
                continue;
            }

            $filePath = $baseDirectory.'/'.$details['file'];

            if (! is_file($filePath)) {
                continue;
            }

            $sizeKb = filesize($filePath) / 1024;

            if ($sizeKb > $largestChunkKb) {
                $largestChunkKb = $sizeKb;
                $largestChunk = $details['file'];
            }

            if (($details['isEntry'] ?? false) === true) {
                $entryTotalKb += $sizeKb;
            }
        }

        return [
            'largest_chunk_kb' => round($largestChunkKb, 1),
            'largest_chunk' => $largestChunk,
            'entry_total_kb' => round($entryTotalKb, 1),
        ];
    }
}
