<?php

namespace Tests\Unit\Support;

use App\Support\BuildManifest;
use Tests\TestCase;

class BuildManifestTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/eq-manifest-'.uniqid();
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    private function writeManifest(array $manifest): string
    {
        $path = $this->directory.'/manifest.json';

        file_put_contents($path, json_encode($manifest));

        return $path;
    }

    private function writeAsset(string $name, int $bytes): void
    {
        file_put_contents($this->directory.'/'.$name, str_repeat('x', $bytes));
    }

    public function test_measures_the_largest_chunk_not_the_sum_of_every_asset(): void
    {
        // Three 300 KB chunks: the largest chunk is 300 KB, but the whole build is 900 KB.
        // The old implementation reported 900 KB against a per-bundle budget.
        foreach (['a.js', 'b.js', 'c.js'] as $name) {
            $this->writeAsset($name, 300 * 1024);
        }

        $path = $this->writeManifest([
            ['file' => 'a.js'],
            ['file' => 'b.js'],
            ['file' => 'c.js'],
        ]);

        $sizes = BuildManifest::frontendSizes($path);

        expect($sizes['largest_chunk'])->toBe('a.js')
            ->and($sizes['largest_chunk_kb'])->toBe(300.0)
            ->and($sizes['entry_total_kb'])->toBe(0.0);
    }

    public function test_sums_only_the_entry_bundles_separately(): void
    {
        $this->writeAsset('app.js', 100 * 1024);
        $this->writeAsset('theme.css', 50 * 1024);
        $this->writeAsset('big-page.js', 700 * 1024);

        $path = $this->writeManifest([
            ['file' => 'app.js', 'isEntry' => true],
            ['file' => 'theme.css', 'isEntry' => true],
            ['file' => 'big-page.js', 'isDynamicEntry' => true],
        ]);

        $sizes = BuildManifest::frontendSizes($path);

        expect($sizes['largest_chunk_kb'])->toBe(700.0)
            ->and($sizes['entry_total_kb'])->toBe(150.0);
    }

    public function test_skips_manifest_entries_whose_file_is_missing(): void
    {
        $this->writeAsset('real.js', 10 * 1024);

        $path = $this->writeManifest([
            ['file' => 'real.js'],
            ['file' => 'ghost.js'],
        ]);

        $sizes = BuildManifest::frontendSizes($path);

        expect($sizes['largest_chunk_kb'])->toBe(10.0)
            ->and($sizes['largest_chunk'])->toBe('real.js');
    }

    public function test_ignores_non_asset_manifest_entries(): void
    {
        $this->writeAsset('real.js', 20 * 1024);

        $path = $this->writeManifest([
            'resources/js/app.js' => ['file' => 'real.js'],
            // Shortcut entries carry no "file" key and must not break the scan.
            '_vendor.js' => ['src' => 'vendor.js', 'isDynamicEntry' => true],
        ]);

        $sizes = BuildManifest::frontendSizes($path);

        expect($sizes['largest_chunk_kb'])->toBe(20.0);
    }

    public function test_returns_zeros_when_the_manifest_is_absent_or_corrupt(): void
    {
        $missing = BuildManifest::frontendSizes($this->directory.'/nope.json');

        expect($missing['largest_chunk_kb'])->toBe(0.0)
            ->and($missing['largest_chunk'])->toBe('')
            ->and($missing['entry_total_kb'])->toBe(0.0);

        $corrupt = $this->directory.'/manifest.json';
        file_put_contents($corrupt, 'not json at all');

        $broken = BuildManifest::frontendSizes($corrupt);

        expect($broken['largest_chunk_kb'])->toBe(0.0);
    }
}
