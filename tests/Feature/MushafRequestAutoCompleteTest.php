<?php

use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use Illuminate\Support\Facades\DB;

/**
 * Ambil id status pengiriman berdasarkan slug, buat bila belum ada.
 * Tabel status_pengiriman bisa kosong di lingkungan test.
 *
 * Nama sengaja spesifik agar tidak bentrok saat seluruh suite dijalankan.
 */
function autoCompleteStatusId(string $slug): int
{
    $existing = DB::table('status_pengiriman')->where('slug', $slug)->value('id');

    if ($existing) {
        return (int) $existing;
    }

    return StatusPengiriman::factory()->create(['slug' => $slug, 'nama' => ucfirst($slug)])->id;
}

it('menyelesaikan permintaan mushaf ketika pengiriman berstatus diterima', function () {
    $pengiriman = Pengiriman::factory()->create();
    $permintaan = MushafRequest::factory()->create([
        'status' => 'processed',
        'pengiriman_id' => $pengiriman->id,
    ]);

    $pengiriman->update(['status_id' => autoCompleteStatusId('diterima')]);

    expect($permintaan->fresh()->status)->toBe('completed');
});

it('tidak menyelesaikan permintaan yang belum diproses', function () {
    // Permintaan yang belum lewat proses (pending/reviewed/approved) tidak boleh
    // ikut "completed" hanya karena tertaut ke pengiriman yang sudah diterima.
    foreach (['pending', 'reviewed', 'approved', 'rejected'] as $status) {
        $pengiriman = Pengiriman::factory()->create();
        $permintaan = MushafRequest::factory()->create([
            'status' => $status,
            'pengiriman_id' => $pengiriman->id,
        ]);

        $pengiriman->update(['status_id' => autoCompleteStatusId('diterima')]);

        expect($permintaan->fresh()->status)->toBe($status);
    }
});

it('tidak menyelesaikan permintaan ketika status pengiriman bukan diterima', function () {
    $pengiriman = Pengiriman::factory()->create();
    $permintaan = MushafRequest::factory()->create([
        'status' => 'processed',
        'pengiriman_id' => $pengiriman->id,
    ]);

    $pengiriman->update(['status_id' => autoCompleteStatusId('pengiriman')]);

    expect($permintaan->fresh()->status)->toBe('processed');
});

it('hanya menyelesaikan permintaan yang tertaut ke pengiriman itu', function () {
    $diterima = Pengiriman::factory()->create();
    $lain = Pengiriman::factory()->create();

    $tertaut = MushafRequest::factory()->create([
        'status' => 'processed',
        'pengiriman_id' => $diterima->id,
    ]);
    $tidakTertaut = MushafRequest::factory()->create([
        'status' => 'processed',
        'pengiriman_id' => $lain->id,
    ]);

    $diterima->update(['status_id' => autoCompleteStatusId('diterima')]);

    expect($tertaut->fresh()->status)->toBe('completed');
    expect($tidakTertaut->fresh()->status)->toBe('processed');
});

it('tidak melakukan apa-apa ketika tidak ada permintaan tertaut', function () {
    $pengiriman = Pengiriman::factory()->create();

    $pengiriman->update(['status_id' => autoCompleteStatusId('diterima')]);

    expect(MushafRequest::where('pengiriman_id', $pengiriman->id)->count())->toBe(0);
});

it('mengisi peta sebaran: permintaan selesai muncul dengan koordinat', function () {
    // Rantai penuh yang diandalkan peta: processed → pengiriman diterima → completed
    $pengiriman = Pengiriman::factory()->create();
    $permintaan = MushafRequest::factory()->create([
        'status' => 'processed',
        'pengiriman_id' => $pengiriman->id,
        'provinsi' => 'DI YOGYAKARTA',
        'latitude' => -7.7956,
        'longitude' => 110.3695,
    ]);

    $pengiriman->update(['status_id' => autoCompleteStatusId('diterima')]);

    // Inilah query yang dipakai peta sebaran
    $mapData = MushafRequest::where('status', 'completed')
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->get();

    expect($mapData)->toHaveCount(1);
    expect($mapData->first()->provinsi)->toBe('DI YOGYAKARTA');
});
