<?php

use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use Database\Seeders\JenisQuranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Satu mushaf = satu resi = satu QR (keputusan pemilik sistem, 15/10/2026).
 *
 * Sebelumnya satu permintaan melahirkan SATU resi berisi seluruh jumlah yang disetujui —
 * EQ-2026-21550 di produksi berisi 80 eks, dan ada yang sampai 500 eks. Akibatnya satu QR
 * mewakili ratusan mushaf, label per mushaf tidak mungkin dicetak, dan alur gudang
 * "pindai tiap mushaf" tidak bisa dipakai.
 *
 * Yang dikunci di sini: permintaan dipecah per EKS dan per JENIS, tiap kiriman ber-QR,
 * mushaf donatur yang belum punya tujuan dipakai lebih dulu TANPA jumlahnya ditimpa, dan
 * permintaan baru selesai setelah SELURUH kirimannya sampai.
 */
beforeEach(function () {
    $role = Role::firstOrCreate(['name' => 'super-admin']);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole($role);

    foreach (['mushaf-requests.process', 'mushaf-requests.update'] as $izin) {
        Permission::firstOrCreate(['name' => $izin]);
    }

    $role->givePermissionTo(['mushaf-requests.process', 'mushaf-requests.update']);

    $this->seed(JenisQuranSeeder::class);

    Storage::fake('local');
    Storage::fake('public');
});

/**
 * Permintaan yang sudah disetujui dengan pecahan per jenis.
 */
function permintaanPecah(int $a5, int $a6, int $iqra = 0): MushafRequest
{
    return MushafRequest::factory()->create([
        'status' => 'approved',
        'jumlah_mushaf_a5' => $a5,
        'jumlah_mushaf_a6' => $a6,
        'jumlah_iqra' => $iqra,
        'jumlah_mushaf_a5_approved' => $a5,
        'jumlah_mushaf_a6_approved' => $a6,
        'jumlah_iqra_approved' => $iqra,
        'jumlah_mushaf_approved' => $a5 + $a6,
    ]);
}

it('memecah kebutuhan per jenis: 20 A5 + 60 A6 menjadi 20 dan 60 kiriman', function () {
    $mushafRequest = permintaanPecah(20, 60);
    $donatur = Donatur::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/mushaf-requests/{$mushafRequest->id}/process", [
            'donatur_id' => $donatur->id,
            'tanggal_wakaf' => now()->format('Y-m-d'),
        ])
        ->assertSessionHasNoErrors();

    $perJenis = Pengiriman::where('mushaf_request_id', $mushafRequest->id)
        ->selectRaw('jenis_quran_id, COUNT(*) as jumlah, COALESCE(SUM(jumlah_quran), 0) as eks')
        ->groupBy('jenis_quran_id')
        ->pluck('jumlah', 'jenis_quran_id');

    $a5 = JenisQuran::where('kode_jenis', 'A5')->value('id');
    $a6 = JenisQuran::where('kode_jenis', 'A6')->value('id');

    expect($perJenis[$a5])->toBe(20)
        ->and($perJenis[$a6])->toBe(60)
        ->and(Pengiriman::where('mushaf_request_id', $mushafRequest->id)->count())->toBe(80)
        // SUM() mengembalikan teks dari MySQL, jadi dibandingkan sebagai angka.
        ->and((int) Pengiriman::where('mushaf_request_id', $mushafRequest->id)->sum('jumlah_quran'))->toBe(80);
});

it('menerima kode jenis IQRO maupun IQRA saat memecah permintaan Iqro', function () {
    // Master produksi memakai kode `IQRO`; dokumen lama menyebut `IQRA`. Bedanya satu huruf,
    // dan dulu itu cukup untuk menggagalkan seluruh pemrosesan.
    $mushafRequest = permintaanPecah(0, 0, 5);
    $donatur = Donatur::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/mushaf-requests/{$mushafRequest->id}/process", [
            'donatur_id' => $donatur->id,
            'tanggal_wakaf' => now()->format('Y-m-d'),
        ])
        ->assertSessionHasNoErrors();

    $iqro = JenisQuran::where('kode_jenis', 'IQRO')->value('id');

    expect(Pengiriman::where('mushaf_request_id', $mushafRequest->id)->where('jenis_quran_id', $iqro)->count())->toBe(5);
});

it('tidak menimpa jumlah eks mushaf donatur yang dipakai', function () {
    // Dulu satu kiriman 1 eks diubah jumlahnya menjadi seluruh jumlah permintaan — itulah
    // asal resi berisi 80 eks. Sekarang satu kiriman tetap satu eks, dan jumlah kiriman
    // yang dibuat menyesuaikan kebutuhan.
    $mushafRequest = permintaanPecah(3, 0);
    $donatur = Donatur::factory()->create();
    $a5 = JenisQuran::where('kode_jenis', 'A5')->value('id');

    $milik = Pengiriman::factory()->count(2)->create([
        'donatur_id' => $donatur->id,
        'jenis_quran_id' => $a5,
        'jumlah_quran' => 1,
        'alamat_tujuan' => null,
        'nama_penerima' => null,
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/mushaf-requests/{$mushafRequest->id}/process", [
            'donatur_id' => $donatur->id,
            'tanggal_wakaf' => now()->format('Y-m-d'),
        ])
        ->assertSessionHasNoErrors();

    $resi = Pengiriman::where('mushaf_request_id', $mushafRequest->id)->get();

    expect($resi)->toHaveCount(3)
        ->and($resi->pluck('jumlah_quran')->unique()->all())->toBe([1])
        // Dua kiriman donatur dipakai (ditandai tujuan), satu dibuat baru.
        ->and($resi->pluck('id')->intersect($milik->pluck('id'))->count())->toBe(2)
        ->and(Pengiriman::whereIn('id', $milik->pluck('id'))->whereNotNull('alamat_tujuan')->count())->toBe(2);
});

it('membuat QR untuk tiap kiriman, dan tidak membuat ulang yang sudah punya', function () {
    $mushafRequest = permintaanPecah(3, 0);
    $donatur = Donatur::factory()->create();
    $a5 = JenisQuran::where('kode_jenis', 'A5')->value('id');

    $sudahAda = Pengiriman::factory()->create([
        'donatur_id' => $donatur->id,
        'jenis_quran_id' => $a5,
        'jumlah_quran' => 1,
        'alamat_tujuan' => null,
        'nama_penerima' => null,
        'qr_code_path' => 'public/qr-codes/QR-sudah-ada.svg',
        'qr_code_data' => 'QR-sudah-ada',
    ]);

    // Berkasnya benar-benar ada: `sudahAda()` memeriksa kolom DAN berkasnya. Kalau berkasnya
    // hilang, QR memang dibuat ulang — itu perilaku yang diinginkan, bukan yang diuji di sini.
    foreach (['local', 'public'] as $disk) {
        Storage::disk($disk)->put('public/qr-codes/QR-sudah-ada.svg', '<svg/>');
    }

    $this->actingAs($this->admin)
        ->post("/admin/mushaf-requests/{$mushafRequest->id}/process", [
            'donatur_id' => $donatur->id,
            'tanggal_wakaf' => now()->format('Y-m-d'),
        ])
        ->assertSessionHasNoErrors();

    $resi = Pengiriman::where('mushaf_request_id', $mushafRequest->id)->get();

    // Yang dibuat baru: 2 kiriman (3 kebutuhan dikurangi 1 yang sudah ber-QR).
    expect($resi->whereNull('qr_code_path')->count())->toBe(0)
        ->and($sudahAda->fresh()->qr_code_path)->toBe('public/qr-codes/QR-sudah-ada.svg')
        ->and($resi->pluck('qr_code_path')->unique()->count())->toBe(3);
});

it('menyelesaikan permintaan hanya setelah SELURUH kirimannya diterima', function () {
    $mushafRequest = permintaanPecah(3, 0);
    $donatur = Donatur::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/mushaf-requests/{$mushafRequest->id}/process", [
            'donatur_id' => $donatur->id,
            'tanggal_wakaf' => now()->format('Y-m-d'),
        ])
        ->assertSessionHasNoErrors();

    $diterima = StatusPengiriman::where('slug', 'diterima')->value('id');
    $resi = Pengiriman::where('mushaf_request_id', $mushafRequest->id)->orderBy('id')->get();

    expect($resi)->toHaveCount(3);

    tibakan($resi[0]);
    $resi[0]->update(['status_id' => $diterima]);
    tibakan($resi[1]);
    $resi[1]->update(['status_id' => $diterima]);

    // Dua dari tiga kiriman sampai: permintaan BELUM selesai.
    expect($mushafRequest->fresh()->status)->toBe('processed');

    tibakan($resi[2]);
    $resi[2]->update(['status_id' => $diterima]);

    expect($mushafRequest->fresh()->status)->toBe('completed');
});

it('menampilkan tahap pengiriman dari kiriman yang paling belakang', function () {
    // Halaman publik tidak boleh mengklaim "Diterima Penerima" hanya karena satu kiriman
    // dari sekian sudah sampai.
    $mushafRequest = permintaanPecah(2, 0);
    $donatur = Donatur::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/mushaf-requests/{$mushafRequest->id}/process", [
            'donatur_id' => $donatur->id,
            'tanggal_wakaf' => now()->format('Y-m-d'),
        ])
        ->assertSessionHasNoErrors();

    $packing = StatusPengiriman::where('slug', 'packing')->value('id');
    $diterima = StatusPengiriman::where('slug', 'diterima')->value('id');
    $resi = Pengiriman::where('mushaf_request_id', $mushafRequest->id)->orderBy('id')->get();

    $resi[0]->update(['status_id' => $packing]);
    tibakan($resi[1]);
    $resi[1]->update(['status_id' => $diterima]);

    $props = $this->get(route('mushaf-tracking.show', $mushafRequest->no_request))
        ->assertSuccessful()
        ->viewData('page')['props'];

    $tahapSekarang = collect($props['shippingStages'])->firstWhere('is_current', true);

    expect($tahapSekarang['slug'])->toBe('packing')
        ->and($props['shippingProgress']['total'])->toBe(2)
        ->and($props['shippingProgress']['diterima'])->toBe(1);
});
