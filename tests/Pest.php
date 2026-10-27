<?php

use App\Models\JenisQuran;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function csrfToken(): string
{
    test()->startSession();

    return app('session.store')->token();
}

/**
 * Bawa resi ke "Tiba di Tujuan" — prasyarat wajib sebelum resi boleh menjadi "Diterima"
 * (Pengiriman::boot(), keputusan pemilik sistem 16/10/2026). Uji yang tentang alur LAIN
 * (notifikasi, penyelesaian permintaan, balapan) memanggil ini dulu, alih-alih menyetel
 * "Diterima" langsung dari "Proses Pengiriman" yang kini ditolak.
 *
 * Ditulis lewat query builder: uji-uji itu tidak sedang menguji serah terima kurir, jadi
 * riwayat/observer langkah perantara ini tidak boleh ikut memengaruhi asersinya.
 *
 * @param  Pengiriman|iterable<Pengiriman>  $resi
 */
function tibakan($resi): void
{
    $tiba = StatusPengiriman::firstOrCreate(
        ['slug' => Pengiriman::SLUG_TIBA],
        ['nama' => 'Tiba di Tujuan', 'warna' => 'indigo', 'urutan' => 7, 'is_active' => true, 'is_final' => false]
    );

    StatusPengiriman::where('slug', 'diterima')->where('urutan', '<=', 7)->update(['urutan' => 8]);

    foreach ($resi instanceof Pengiriman ? [$resi] : $resi as $p) {
        DB::table('pengiriman')->where('id', $p->id)->update(['status_id' => $tiba->id]);
        $p->refresh();
    }
}

/**
 * Isian jenis wajib untuk POST assign-target (sejak 28/10/2026). Uji yang tentang aturan
 * LAIN (gudang, hierarki penugasan) memakai Iqro: jenis tanpa varian, jadi cukup satu id.
 *
 * @return array{jenis_quran_id: int}
 */
function jenisTugasUji(): array
{
    $iqra = JenisQuran::where('kode_jenis', 'IQRA')->first() ?? JenisQuran::factory()->iqra()->create();
    $iqra->update(['is_active' => true]);

    return ['jenis_quran_id' => $iqra->id];
}

function something()
{
    // ..
}
