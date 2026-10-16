<?php

use App\Imports\DonaturImport;
use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Models\WakafItem;
use App\Services\DonaturImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

/**
 * Berkas contoh 10 data dummy (tests/fixtures/data-dummy-donatur-10.xlsx).
 *
 * Berkas ini dipakai untuk MENGHADAPKAN seluruh kemampuan templat baru sekaligus, dan
 * untuk dibagikan ke tim entry sebagai gambaran cara mengisi. Karena itu ia harus
 * benar-benar bisa diimpor — bukan hanya terlihat benar. Kalau templat berubah dan
 * berkas contohnya jadi basi, orang pertama yang tahu adalah tim entry, di produksi.
 *
 * Berkasnya berisi 22 baris untuk 10 donatur (satu donatur boleh beberapa baris).
 */
beforeEach(function () {
    JenisQuran::firstOrCreate(['kode_jenis' => 'A5'], ['nama_jenis' => 'Al-Quran A5', 'is_active' => true, 'harga' => 50000]);
    JenisQuran::firstOrCreate(['kode_jenis' => 'A6'], ['nama_jenis' => 'Al-Quran A6', 'is_active' => true, 'harga' => 35000]);
    JenisQuran::firstOrCreate(['kode_jenis' => 'IQRA'], ['nama_jenis' => 'Iqra', 'is_active' => true, 'harga' => 25000]);

    StatusPengiriman::firstOrCreate(
        ['slug' => 'pemesanan'],
        ['nama' => 'Proses Pemesanan', 'deskripsi' => 'Default', 'warna' => 'purple', 'urutan' => 1, 'is_active' => true, 'is_final' => false]
    );

    cache()->forget('jenis_quran_mapping');

    $this->user = User::factory()->create(['is_active' => true]);
    $this->actingAs($this->user);
});

function imporDummy(): array
{
    $path = base_path('tests/fixtures/data-dummy-donatur-10.xlsx');

    expect(file_exists($path))->toBeTrue("Berkas contoh tidak ditemukan: {$path}");

    $import = new DonaturImport(new DonaturImportService);
    Excel::import($import, $path);

    return $import->getResults();
}

it('berkas contoh 10 donatur dapat diimpor tanpa satu pun galat', function () {
    $hasil = imporDummy();

    expect($hasil['error_count'])->toBe(0)
        ->and($hasil['success_count'])->toBe(10)
        ->and($hasil['mushaf_count'])->toBe(146);
});

it('berkas contoh menghasilkan angka yang persis seperti yang tertulis di ringkasan', function () {
    imporDummy();

    // Angka per donatur, termasuk kasus 50 mushaf dalam satu baris.
    $harusnya = [
        'DMY001' => ['baris' => 1, 'a5' => 2,  'a6' => 0,  'iqra' => 0],
        'DMY002' => ['baris' => 1, 'a5' => 3,  'a6' => 1,  'iqra' => 2],
        'DMY003' => ['baris' => 2, 'a5' => 15, 'a6' => 0,  'iqra' => 0],
        'DMY004' => ['baris' => 3, 'a5' => 8,  'a6' => 4,  'iqra' => 0],
        'DMY005' => ['baris' => 1, 'a5' => 50, 'a6' => 0,  'iqra' => 0],
        'DMY006' => ['baris' => 5, 'a5' => 5,  'a6' => 0,  'iqra' => 0],
        'DMY007' => ['baris' => 1, 'a5' => 0,  'a6' => 0,  'iqra' => 10],
        'DMY008' => ['baris' => 1, 'a5' => 0,  'a6' => 20, 'iqra' => 0],
        'DMY009' => ['baris' => 3, 'a5' => 2,  'a6' => 3,  'iqra' => 5],
        'DMY010' => ['baris' => 4, 'a5' => 10, 'a6' => 2,  'iqra' => 4],
    ];

    foreach ($harusnya as $kode => $angka) {
        $d = Donatur::where('kode_donatur', $kode)->first();

        expect($d)->not->toBeNull("Donatur {$kode} tidak terbuat");

        expect($d->total_a5_count)->toBe($angka['a5'], "A5 {$kode}")
            ->and($d->total_a6_count)->toBe($angka['a6'], "A6 {$kode}")
            ->and($d->total_iqra_count)->toBe($angka['iqra'], "Iqra {$kode}")
            // Berapa pun barisnya, tetap SATU donasi.
            ->and($d->donation_count)->toBe(1, "donation_count {$kode}")
            ->and(WakafItem::where('donatur_id', $d->id)->count())
            ->toBe($angka['a5'] + $angka['a6'] + $angka['iqra'], "jumlah item {$kode}")
            ->and(Pengiriman::where('donatur_id', $d->id)->count())
            ->toBe($angka['a5'] + $angka['a6'] + $angka['iqra'], "jumlah resi {$kode}");
    }
});

it('berkas contoh mencakup setiap kemampuan templat yang baru', function () {
    imporDummy();

    // 1. Berulang: satu donatur ditulis beberapa baris.
    expect(WakafItem::whereHas('donatur', fn ($q) => $q->where('kode_donatur', 'DMY010'))->count())->toBe(16);

    // 2. Nama wakif berbeda dalam satu donatur benar-benar terpisah.
    $d3 = Donatur::where('kode_donatur', 'DMY003')->firstOrFail();
    expect(WakafItem::where('donatur_id', $d3->id)->where('wakif_name', 'Alm. H. Abdul Karim')->count())->toBe(10)
        ->and(WakafItem::where('donatur_id', $d3->id)->where('wakif_name', 'Hj. Nurhayati')->count())->toBe(5)
        // Nama wakif dikosongkan -> jatuh ke nama donatur, bukan kosong.
        ->and(WakafItem::where('donatur_id', $d3->id)->whereNull('wakif_name')->count())->toBe(0);

    // 3. Hubungan dengan donatur tersimpan dan berbeda-beda.
    $d9 = Donatur::where('kode_donatur', 'DMY009')->firstOrFail();
    foreach (['Almarhum', 'Diri sendiri', 'Lainnya'] as $hubungan) {
        expect(WakafItem::where('donatur_id', $d9->id)->where('relationship_to_donatur', $hubungan)->exists())
            ->toBeTrue("hubungan {$hubungan} tidak tersimpan");
    }

    // 4. Donatur besar: 50 mushaf dalam SATU baris.
    $d5 = Donatur::where('kode_donatur', 'DMY005')->firstOrFail();
    expect($d5->total_a5_count)->toBe(50)
        ->and($d5->prayer_mode)->toBe('semua_donatur')
        ->and(WakafItem::where('donatur_id', $d5->id)->where('wakif_name', 'Yayasan Cahaya Ilmu')->count())->toBe(50);

    // 5. Nomor berjalan tanpa kembar di setiap donatur. Ada DUA penomoran dan keduanya
    //    harus benar:
    //      - `sequence_in_type`  : per jenis, jadi A5 #1..n, lalu A6 #1..n sendiri
    //      - `global_sequence`   : lintas jenis, jadi 1..total mushaf donatur itu
    foreach (Donatur::with('wakafItems')->get() as $donatur) {
        foreach (['A5', 'A6', 'IQRA'] as $jenis) {
            $nomor = $donatur->wakafItems
                ->where('wakaf_type', $jenis)
                ->sortBy('sequence_in_type')
                ->pluck('sequence_in_type')
                ->values()
                ->all();

            // Jenis yang tidak dipakai memang kosong. Perhatikan `range(1, 0)` di PHP
            // menghasilkan [1, 0], BUKAN array kosong — jadi harus diperiksa terpisah.
            $harusnya = $nomor === [] ? [] : range(1, count($nomor));

            expect($nomor)->toBe($harusnya, "nomor {$jenis} tidak berurutan pada donatur {$donatur->kode_donatur}");
        }

        $global = $donatur->wakafItems->sortBy('global_sequence')->pluck('global_sequence')->values()->all();

        expect($global)->toBe(range(1, count($global)), "nomor tampil (#N) tidak berurutan pada donatur {$donatur->kode_donatur}");
    }
});

it('tanggal format Indonesia dan format ISO sama-sama terbaca', function () {
    imporDummy();

    // DMY003 memakai 2026-01-07, DMY004 memakai 15/01/2026.
    expect(Donatur::where('kode_donatur', 'DMY003')->firstOrFail()->donation_date->format('Y-m-d'))->toBe('2026-01-07')
        ->and(Donatur::where('kode_donatur', 'DMY004')->firstOrFail()->donation_date->format('Y-m-d'))->toBe('2026-01-15');
});

it('nomor HP dari tiga gaya penulisan diseragamkan ke +62', function () {
    imporDummy();

    // 0812…, 62812…, dan +62812… semuanya ditulis di berkas contoh.
    foreach (['DMY001', 'DMY003', 'DMY002'] as $kode) {
        expect(Donatur::where('kode_donatur', $kode)->firstOrFail()->no_hp)
            ->toStartWith('+62', "nomor HP {$kode}");
    }
});
