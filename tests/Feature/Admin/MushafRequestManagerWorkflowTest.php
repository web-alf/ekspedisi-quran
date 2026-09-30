<?php

use App\Models\DailyPackingTask;
use App\Models\Donatur;
use App\Models\MushafRequest;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use Database\Seeders\JenisQuranSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * Paket A — perbaikan "tombol edit jumlah" pada halaman permintaan mushaf.
 *
 * Bug asli: halaman detail membaca `jumlah_mushaf_approved`, sedangkan modal edit
 * mengirim `jumlah_mushaf_approved` apa adanya dari nilai lama (ditambah pecahan
 * A5/A6/IQRA). Akibatnya angka "Total Disetujui" tidak pernah berubah walau
 * pecahannya diedit.
 *
 * Paket B — tahap "Selesai Packing" → "Diterima Penerima" untuk permintaan mushaf.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(JenisQuranSeeder::class);

    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->assignRole('manager');
});

describe('Paket A — edit jumlah disetujui', function () {
    test('tombol edit jumlah tampil untuk manager dan mengikuti izin, bukan status', function () {
        $request = MushafRequest::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->manager)
            ->get(route('admin.mushaf-requests.show', $request));

        $response->assertSuccessful();

        // `can.mushafRequests.update` inilah yang dipakai view untuk merender tombol,
        // bukan lagi daftar status hardcoded ('approved'/'reviewed').
        expect($response->viewData('page')['props']['auth']['user']['permissions'] ?? [])
            ->toContain('mushaf-requests.update');
    });

    test('akumulasi total disetujui berubah setelah jumlah diedit', function () {
        $request = MushafRequest::factory()->create([
            'status' => 'approved',
            'jumlah_mushaf_a5' => 60,
            'jumlah_mushaf_a6' => 0,
            'jumlah_iqra' => 0,
            'jumlah_mushaf_approved' => null,
        ]);

        // Sebelum diedit: yang tampil adalah jumlah pengajuan.
        $sebelum = $this->actingAs($this->manager)
            ->get(route('admin.mushaf-requests.show', $request));

        $sebelum->assertSuccessful();
        expect($sebelum->viewData('page')['props']['mushafRequest']['approved_breakdown']['total'])->toBe(60);

        // Edit: Total Disetujui = 10 (A5) + 5 (IQRA) = 15.
        $this->actingAs($this->manager)
            ->patch(route('admin.mushaf-requests.update-quantities', $request), [
                'jumlah_mushaf_a5_approved' => 10,
                'jumlah_mushaf_a6_approved' => 0,
                'jumlah_iqra_approved' => 5,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $request->refresh();

        expect($request->jumlah_mushaf_approved)->toBe(15)
            ->and($request->approved_breakdown['total'])->toBe(15)
            ->and($request->approved_breakdown['a5'])->toBe(10)
            ->and($request->has_quantity_change)->toBeTrue();

        // Halaman detail menampilkan angka yang sama dengan yang tersimpan.
        $sesudah = $this->actingAs($this->manager)
            ->get(route('admin.mushaf-requests.show', $request));

        expect($sesudah->viewData('page')['props']['mushafRequest']['approved_breakdown']['total'])->toBe(15);
    });

    test('proses ke pengiriman memakai jumlah disetujui yang sama dengan tampilan', function () {
        $request = MushafRequest::factory()->create([
            'status' => 'approved',
            'jumlah_mushaf_a5' => 60,
            'jumlah_mushaf_a6' => 0,
            'jumlah_iqra' => 0,
            'jumlah_mushaf_approved' => null,
        ]);

        $this->actingAs($this->manager)
            ->patch(route('admin.mushaf-requests.update-quantities', $request), [
                'jumlah_mushaf_a5_approved' => 12,
                'jumlah_mushaf_a6_approved' => 0,
                'jumlah_iqra_approved' => 3,
            ])
            ->assertSessionHasNoErrors();

        $donatur = Donatur::factory()->create();

        $this->actingAs($this->manager)
            ->post(route('admin.mushaf-requests.process', $request), [
                'donatur_id' => $donatur->id,
                'tanggal_wakaf' => now()->format('Y-m-d'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(Pengiriman::where('donatur_id', $donatur->id)->value('jumlah_quran'))->toBe(15);
    });

    test('permintaan tanpa jumlah disetujui tetap bisa diproses (regresi penjaga lama)', function () {
        // Data produksi: 28 dari 28 permintaan belum pernah ditetapkan jumlah
        // disetujuinya. Penjaga lama (`has_quantity_change && is_null(...)`)
        // memblokir semuanya sehingga tidak ada permintaan yang bisa diproses.
        $request = MushafRequest::factory()->create([
            'status' => 'approved',
            'jumlah_mushaf_a5' => 20,
            'jumlah_mushaf_a6' => 0,
            'jumlah_iqra' => 0,
            'jumlah_mushaf_approved' => null,
        ]);

        $donatur = Donatur::factory()->create();

        $this->actingAs($this->manager)
            ->post(route('admin.mushaf-requests.process', $request), [
                'donatur_id' => $donatur->id,
                'tanggal_wakaf' => now()->format('Y-m-d'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(Pengiriman::where('donatur_id', $donatur->id)->value('jumlah_quran'))->toBe(20);
    });
});

describe('Paket B — tahap selesai packing sampai diterima penerima', function () {
    test('menyegel kerdus menaikkan pengiriman dari packing ke selesai-packing', function () {
        $packingId = StatusPengiriman::query()->where('slug', 'packing')->value('id');
        $readySlug = 'selesai-packing';

        expect($packingId)->not->toBeNull('status packing harus ada di master status');

        $pengiriman = Pengiriman::factory()->create(['status_id' => $packingId]);

        $box = PackingBox::factory()->create([
            'status' => PackingBox::STATUS_FILLING,
            'jumlah_terisi' => 1,
            'daily_packing_task_id' => DailyPackingTask::factory(),
        ]);

        PackingItem::factory()->create([
            'packing_box_id' => $box->id,
            'pengiriman_id' => $pengiriman->id,
        ]);

        $box->refresh()->seal();

        expect(StatusPengiriman::query()->whereKey($pengiriman->fresh()->status_id)->value('slug'))
            ->toBe($readySlug);
    });

    test('pengiriman yang sudah dikirim tidak mundur saat kerdus disegel', function () {
        $packingId = StatusPengiriman::query()->where('slug', 'packing')->value('id');
        $sentId = StatusPengiriman::query()->where('slug', 'pengiriman')->value('id');

        $pengiriman = Pengiriman::factory()->create(['status_id' => $sentId]);

        $box = PackingBox::factory()->create([
            'status' => PackingBox::STATUS_FILLING,
            'jumlah_terisi' => 1,
            'daily_packing_task_id' => DailyPackingTask::factory(),
        ]);

        PackingItem::factory()->create([
            'packing_box_id' => $box->id,
            'pengiriman_id' => $pengiriman->id,
        ]);

        $box->refresh()->seal();

        expect($pengiriman->fresh()->status_id)->toBe($sentId);
    });

    test('halaman tracking permintaan menampilkan tahap packing sampai diterima', function () {
        $packingId = StatusPengiriman::query()->where('slug', 'packing')->value('id');

        $request = MushafRequest::factory()->create([
            'status' => 'processed',
            'jumlah_mushaf_approved' => 10,
        ]);

        $pengiriman = Pengiriman::factory()->create([
            'status_id' => $packingId,
            'mushaf_request_id' => $request->id,
        ]);

        $request->update(['pengiriman_id' => $pengiriman->id]);

        $response = $this->get(route('mushaf-tracking.show', $request->no_request));

        $response->assertSuccessful();

        $stages = $response->viewData('page')['props']['shippingStages'];

        expect(collect($stages)->pluck('slug')->all())
            ->toContain('packing')
            ->toContain('selesai-packing')
            ->toContain('pengiriman')
            ->toContain('diterima')
            ->and(collect($stages)->firstWhere('slug', 'packing')['reached'])->toBeTrue()
            ->and(collect($stages)->firstWhere('slug', 'diterima')['reached'])->toBeFalse();
    });

    test('permintaan selesai otomatis saat pengiriman berstatus diterima', function () {
        $packingId = StatusPengiriman::query()->where('slug', 'packing')->value('id');
        $receivedId = StatusPengiriman::query()->where('slug', 'diterima')->value('id');

        $request = MushafRequest::factory()->create(['status' => 'processed']);

        $pengiriman = Pengiriman::factory()->create([
            'status_id' => $packingId,
            'mushaf_request_id' => $request->id,
        ]);

        $request->update(['pengiriman_id' => $pengiriman->id]);

        $pengiriman->update(['status_id' => $receivedId]);

        expect($request->fresh()->status)->toBe('completed');
    });
});
