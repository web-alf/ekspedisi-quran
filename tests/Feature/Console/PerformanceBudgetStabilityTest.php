<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * performance:check-budgets tidak boleh melaporkan kegagalan hanya karena cara
 * mengukurnya sendiri salah.
 *
 * Dua cacat yang membuat job terjadwal ini gagal SETIAP tengah malam (8 malam
 * berturut-turut, 25 Sep - 2 Okt) padahal tidak ada perubahan performa:
 *   1. Uji rasio cache memakai Cache::has() SEBELUM mengisi kuncinya, sehingga
 *      10 pembacaan pertama selalu meleset dan rasionya SELALU 0,5 - di bawah
 *      ambang 0,8. Terukur: 0,5 pada jalan pertama, 1,0 pada jalan berikutnya.
 *   2. Ambang waktu query COUNT 10 ms berada di bawah derau pengukuran pada
 *      server 1 vCPU (terukur 7,4 ms sampai 11,4 ms antar-jalan).
 */
it('lulus pada jalan PERTAMA, bukan hanya pada jalan berikutnya', function () {
    // Inilah regresinya: dulu jalan pertama selalu merah karena rasio cache 0,5.
    $this->artisan('performance:check-budgets')->assertSuccessful();
});

it('memberi hasil yang sama pada dua jalan berturut-turut', function () {
    $this->artisan('performance:check-budgets')->assertSuccessful();
    $this->artisan('performance:check-budgets')->assertSuccessful();
});

it('melaporkan rasio cache 1,0 pada jalan pertama', function () {
    // Angka inilah yang dulu selalu 0,5 lalu memicu pelanggaran palsu.
    $this->artisan('performance:check-budgets')
        ->expectsOutputToContain('Cache hit ratio: 1')
        ->assertSuccessful();
});

it('membersihkan kunci ujinya, tidak menumpuk di cache', function () {
    $this->artisan('performance:check-budgets')->assertSuccessful();
    $this->artisan('performance:check-budgets')->assertSuccessful();

    expect(Cache::has('budget_ratio_test'))->toBeFalse();
});

it('tidak lagi memakai kunci uji lama yang menumpuk', function () {
    // Kunci test_key_N adalah sisa cara lama; tidak boleh dipakai lagi.
    $this->artisan('performance:check-budgets')->assertSuccessful();

    expect(Cache::has('test_key_0'))->toBeFalse()
        ->and(Cache::has('test_key_9'))->toBeFalse();
});

it('lulus persis seperti perintah terjadwal produksi (--output=json)', function () {
    // routes/console.php menjadwalkan: performance:check-budgets --output=json
    // tanpa --strict. Mode inilah yang harus hijau; mode --strict memang lebih
    // ketat dan tidak dipakai harian.
    $this->artisan('performance:check-budgets', ['--output' => 'json'])->assertSuccessful();
});

it('memakai ambang waktu query di atas derau pengukuran server', function () {
    // Server produksi 1 vCPU; pengukuran berkisar 7-11 ms. Ambang harus di atas
    // itu supaya laporannya tidak untung-untungan, tapi tetap cukup ketat untuk
    // menangkap kemunduran nyata.
    $berkas = file_get_contents(app_path('Console/Commands/Performance/CheckBudgetsCommand.php'));

    preg_match("/'simple_count_max_time' => \\\$strict \\? (\d+) : (\d+)/", $berkas, $m);

    expect($m)->not->toBeEmpty('ambang simple_count tidak ditemukan');

    $ketat = (int) $m[1];
    $longgar = (int) $m[2];

    expect($ketat)->toBeGreaterThan(10)
        ->and($longgar)->toBeGreaterThanOrEqual(25)
        // Tetap ada jaring: jangan sampai dilonggarkan sampai tak berguna.
        ->and($longgar)->toBeLessThanOrEqual(100);
});
