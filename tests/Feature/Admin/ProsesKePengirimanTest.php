<?php

use App\Models\Donatur;
use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Models\User;
use Database\Seeders\JenisQuranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Langkah "Proses ke Pengiriman" pada permintaan mushaf.
 *
 * Seluruh berkas ini menjaga satu hal: permintaan yang sudah disetujui harus
 * punya SATU jalan menuju kiriman, dan jalan itu harus benar-benar membuat
 * kiriman. Dua cacat yang pernah ada di sini sama-sama diam-diam:
 *
 *   1. Peta status di halaman detail menawarkan "Sudah Diproses" sebagai langkah
 *      berikutnya setelah "Disetujui". Backend hanya menulis statusnya, tanpa
 *      membuat kiriman — sementara `processToShipment` menolak apa pun yang
 *      statusnya bukan `approved`. Sekali admin memilihnya, permintaan itu
 *      terperangkap: berlabel "Sudah Diproses" padahal tidak ada kiriman, dan
 *      tidak bisa diproses lagi selamanya.
 *   2. `processToShipment` hanya menulis `mushaf_requests.pengiriman_id`, tidak
 *      `pengiriman.mushaf_request_id`. Dua jalur lain (set alamat massal dan
 *      pemindai kerdus gudang) menulis keduanya, jadi kiriman hasil halaman
 *      permintaan tidak bisa ditelusuri balik ke asal permintaannya.
 */
beforeEach(function () {
    $role = Role::firstOrCreate(['name' => 'super-admin']);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole($role);

    foreach (['mushaf-requests.process', 'mushaf-requests.update', 'mushaf-requests.approve'] as $izin) {
        Permission::firstOrCreate(['name' => $izin]);
    }

    $role->givePermissionTo(['mushaf-requests.process', 'mushaf-requests.update', 'mushaf-requests.approve']);

    $this->seed(JenisQuranSeeder::class);
});

function permintaanSiapProses(int $jumlahDisetujui = 80): MushafRequest
{
    return MushafRequest::factory()->create([
        'status' => 'approved',
        'jumlah_mushaf_a5' => 100,
        'jumlah_mushaf_a6' => 0,
        'jumlah_iqra' => 0,
        'jumlah_mushaf_a5_approved' => $jumlahDisetujui,
        'jumlah_mushaf_a6_approved' => 0,
        'jumlah_iqra_approved' => 0,
        'jumlah_mushaf_approved' => $jumlahDisetujui,
    ]);
}

it('menautkan kiriman balik ke permintaan saat diproses', function () {
    $mushafRequest = permintaanSiapProses();
    $donatur = Donatur::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/mushaf-requests/{$mushafRequest->id}/process", [
            'donatur_id' => $donatur->id,
            'tanggal_wakaf' => now()->format('Y-m-d'),
        ])
        ->assertRedirect();

    $mushafRequest->refresh();
    $pengiriman = Pengiriman::find($mushafRequest->pengiriman_id);

    expect($pengiriman)->not->toBeNull()
        // Arah balik: dari kiriman harus bisa ditemukan permintaan asalnya.
        // Tanpa ini, halaman kiriman dan alur gudang tidak tahu kiriman ini
        // melayani permintaan lembaga yang mana.
        ->and($pengiriman->mushaf_request_id)->toBe($mushafRequest->id)
        ->and($pengiriman->nama_lembaga)->toBe($mushafRequest->nama_lembaga)
        ->and($pengiriman->jumlah_quran)->toBe(80);
});

it('menolak status "processed" diubah langsung dari peta status', function () {
    // Permintaan ini disetujui tetapi BELUM punya kiriman.
    $mushafRequest = permintaanSiapProses();

    $this->actingAs($this->admin)
        ->patch("/admin/mushaf-requests/{$mushafRequest->id}/status", [
            'status' => 'processed',
        ])
        ->assertSessionHasErrors();

    $mushafRequest->refresh();

    expect($mushafRequest->status)->toBe('approved')
        ->and($mushafRequest->pengiriman_id)->toBeNull();
});

it('tidak mengubah status permintaan menjadi processed tanpa kiriman', function () {
    $mushafRequest = permintaanSiapProses();

    $this->actingAs($this->admin)
        ->patch("/admin/mushaf-requests/{$mushafRequest->id}/status", [
            'status' => 'processed',
        ]);

    // Inti jebakannya: permintaan berlabel "Sudah Diproses" padahal tak ada
    // kiriman apa pun, dan statusnya sudah bukan 'approved' sehingga
    // processToShipment akan menolaknya selamanya.
    expect(MushafRequest::where('status', 'processed')->whereNull('pengiriman_id')->count())->toBe(0);
});

it('tetap mengizinkan status lain yang sah', function () {
    $mushafRequest = MushafRequest::factory()->create([
        'status' => 'pending',
        'jumlah_mushaf_a5' => 50,
        'jumlah_mushaf_a6' => 0,
        'jumlah_iqra' => 0,
    ]);

    $this->actingAs($this->admin)
        ->patch("/admin/mushaf-requests/{$mushafRequest->id}/status", [
            'status' => 'approved',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($mushafRequest->fresh()->status)->toBe('approved');
});

it('mengirim daftar donatur ke halaman detail untuk modal pemrosesan', function () {
    $mushafRequest = permintaanSiapProses();
    Donatur::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get("/admin/mushaf-requests/{$mushafRequest->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/MushafRequest/Show')
            ->has('donaturList', 3)
        );
});
