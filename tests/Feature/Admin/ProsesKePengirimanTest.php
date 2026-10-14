<?php

use App\Http\Controllers\Admin\MushafRequestController;
use App\Models\Donatur;
use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Models\User;
use Database\Seeders\JenisQuranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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

it('menolak memproses permintaan yang sudah punya kiriman', function () {
    // Keadaan ini nyata di produksi: 10 permintaan sudah tertaut ke sebuah
    // kiriman tetapi statusnya masih `approved`. Tanpa penjaga ini, tombol
    // "Proses ke Pengiriman" ikut tampil untuk mereka, dan menekannya membuat
    // kiriman KEDUA untuk lembaga yang sama sambil memindahkan tautannya —
    // sehingga kiriman lama kehilangan asal permintaannya.
    $mushafRequest = permintaanSiapProses();
    $kirimanLama = Pengiriman::factory()->create(['mushaf_request_id' => $mushafRequest->id]);
    $mushafRequest->update(['pengiriman_id' => $kirimanLama->id]);

    $donatur = Donatur::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/mushaf-requests/{$mushafRequest->id}/process", [
            'donatur_id' => $donatur->id,
            'tanggal_wakaf' => now()->format('Y-m-d'),
        ])
        ->assertSessionHasErrors();

    expect($mushafRequest->fresh()->pengiriman_id)->toBe($kirimanLama->id)
        ->and(Pengiriman::count())->toBe(1);
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

/**
 * Race condition: penjaga `status`/`pengiriman_id` di awal processToShipment
 * membaca instance hasil route binding tanpa kunci. Dua klik "Proses" atau dua
 * CS bersamaan sama-sama lolos, dan masing-masing membuat kiriman. Request
 * kedua ditiru dengan memanggil controller memakai instance basi yang dimuat
 * sebelum request pertama selesai.
 */
it('tidak membuat kiriman kedua saat permintaan diproses bersamaan', function () {
    $mushafRequest = permintaanSiapProses();
    $donatur = Donatur::factory()->create();
    $basi = MushafRequest::find($mushafRequest->id);

    $this->actingAs($this->admin)
        ->post("/admin/mushaf-requests/{$mushafRequest->id}/process", [
            'donatur_id' => $donatur->id,
            'tanggal_wakaf' => now()->format('Y-m-d'),
        ])
        ->assertRedirect();

    $kirimanPertama = $mushafRequest->fresh()->pengiriman_id;

    $respons = app(MushafRequestController::class)->processToShipment(
        Request::create('/', 'POST', [
            'donatur_id' => $donatur->id,
            'tanggal_wakaf' => now()->format('Y-m-d'),
        ]),
        $basi,
    );

    expect($respons->getSession()->get('errors')->first('error'))->toContain('baru saja diproses')
        ->and(Pengiriman::count())->toBe(1)
        ->and($mushafRequest->fresh()->pengiriman_id)->toBe($kirimanPertama);
});

it('memakai kiriman kosong milik donatur alih-alih membuat kiriman baru', function () {
    // Cabang ini dulu selalu gagal: `fresh(['alamat_tujuan', ...])` menganggap
    // nama kolom sebagai nama relasi dan melempar RelationNotFoundException.
    $mushafRequest = permintaanSiapProses();
    $donatur = Donatur::factory()->create();
    $kirimanKosong = Pengiriman::factory()->create([
        'donatur_id' => $donatur->id,
        'alamat_tujuan' => null,
        'nama_penerima' => null,
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/mushaf-requests/{$mushafRequest->id}/process", [
            'donatur_id' => $donatur->id,
            'tanggal_wakaf' => now()->format('Y-m-d'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($mushafRequest->fresh()->pengiriman_id)->toBe($kirimanKosong->id)
        ->and(Pengiriman::count())->toBe(1)
        ->and($kirimanKosong->fresh()->mushaf_request_id)->toBe($mushafRequest->id)
        ->and($kirimanKosong->fresh()->alamat_tujuan)->toBe($mushafRequest->alamat_lengkap);
});

function beriIzinSetAlamatMassal(User $admin): void
{
    Permission::firstOrCreate(['name' => 'shipments.bulk-update']);
    $admin->givePermissionTo('shipments.bulk-update');
}

it('menautkan permintaan ke kiriman lewat set alamat massal', function () {
    beriIzinSetAlamatMassal($this->admin);
    $mushafRequest = permintaanSiapProses();
    $kiriman = Pengiriman::factory()->count(2)->create(['alamat_tujuan' => null]);

    $this->actingAs($this->admin)
        ->post(route('admin.pengiriman.bulk-alamat'), [
            'pengiriman_ids' => $kiriman->pluck('id')->all(),
            'alamat_tujuan' => 'Jl. Masjid No. 1',
            'mushaf_request_id' => $mushafRequest->id,
        ])
        ->assertSessionHasNoErrors();

    $mushafRequest->refresh();

    expect($mushafRequest->status)->toBe('processed')
        ->and($mushafRequest->pengiriman_id)->toBe($kiriman->min('id'))
        ->and(Pengiriman::where('mushaf_request_id', $mushafRequest->id)->count())->toBe(2);
});

it('menolak set alamat massal untuk permintaan yang sudah diproses dan tidak mengubah apa pun', function () {
    // Kalau dua CS menautkan permintaan yang sama bersamaan, yang kedua melihat
    // permintaan sudah diproses (setelah menunggu kunci) dan seluruh perubahannya
    // dibatalkan — bukan menautkan set resi kedua ke permintaan yang sama.
    beriIzinSetAlamatMassal($this->admin);
    $mushafRequest = permintaanSiapProses();
    $kirimanPertama = Pengiriman::factory()->create(['mushaf_request_id' => $mushafRequest->id]);
    $mushafRequest->markAsProcessed($kirimanPertama->id, $this->admin->id);

    $kirimanLain = Pengiriman::factory()->create(['alamat_tujuan' => 'Alamat lama']);

    $this->actingAs($this->admin)
        ->post(route('admin.pengiriman.bulk-alamat'), [
            'pengiriman_ids' => [$kirimanLain->id],
            'alamat_tujuan' => 'Alamat baru',
            'mushaf_request_id' => $mushafRequest->id,
        ])
        ->assertSessionHasErrors('error');

    expect($kirimanLain->fresh()->alamat_tujuan)->toBe('Alamat lama')
        ->and($kirimanLain->fresh()->mushaf_request_id)->toBeNull()
        ->and($mushafRequest->fresh()->pengiriman_id)->toBe($kirimanPertama->id);
});
