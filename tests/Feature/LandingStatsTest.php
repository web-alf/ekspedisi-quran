<?php

use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\Assert as AssertableInertia;

uses(RefreshDatabase::class);

it('menghitung Mushaf Tersalurkan dari pengiriman status diterima', function () {
    // Buat status 'diterima'
    $statusDiterima = StatusPengiriman::factory()->create([
        'nama' => 'Diterima',
        'slug' => 'diterima',
        'is_active' => true,
    ]);

    // Request 1 + pengiriman diterima (jumlah_quran = 15)
    $req1 = MushafRequest::factory()->completed()->create([
        'provinsi' => 'Jawa Barat',
        'kota_kabupaten' => 'Bandung',
        'latitude' => -6.9175,
        'longitude' => 107.6191,
    ]);
    $ship1 = Pengiriman::factory()->create([
        'status_id' => $statusDiterima->id,
        'jumlah_quran' => 15,
        'nama_lembaga' => $req1->nama_lembaga,
    ]);
    // Link ke request untuk ambil koordinat peta
    $req1->update(['pengiriman_id' => $ship1->id]);

    // Request 2 + pengiriman diterima (jumlah_quran = 25)
    $req2 = MushafRequest::factory()->completed()->create([
        'provinsi' => 'Jawa Tengah',
        'kota_kabupaten' => 'Semarang',
        'latitude' => -7.0051,
        'longitude' => 110.4381,
    ]);
    $ship2 = Pengiriman::factory()->create([
        'status_id' => $statusDiterima->id,
        'jumlah_quran' => 25,
        'nama_lembaga' => $req2->nama_lembaga,
    ]);
    $req2->update(['pengiriman_id' => $ship2->id]);

    // Pengiriman belum diterima (harus diabaikan) dan request TIDAK completed agar tidak muncul di peta
    $req3 = MushafRequest::factory()->create([
        'status' => 'approved',
        'provinsi' => 'Jawa Barat',
        'kota_kabupaten' => 'Depok',
        'latitude' => -6.39,
        'longitude' => 106.82,
    ]);
    $ship3 = Pengiriman::factory()->create([
        'jumlah_quran' => 100,
        'nama_lembaga' => $req3->nama_lembaga,
    ]);
    $req3->update(['pengiriman_id' => $ship3->id]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) =>
        $page->component('Landing')
            ->has('stats')
            ->where('stats.totalMushaf', 40) // 15 + 25 dari pengiriman diterima saja
            ->has('mapData')
            ->where('mapData', function ($map) {
                // Hanya 2 pengiriman diterima yang punya koordinat harus tampil
                return is_array($map) && count($map) === 2;
            })
    );
});
