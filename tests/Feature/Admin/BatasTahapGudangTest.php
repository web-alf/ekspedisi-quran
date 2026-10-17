<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Support\PengirimanStageVisibility;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Tahap gudang hasil rombakan 2026-10-13: Pemesanan → Antrian → Packing →
 * Selesai Packing. `kedatangan` dihapus dari alur (master dinonaktifkan) dan
 * `produksi` keluar dari ranah gudang (proses cetak di luar gudang).
 *
 * Yang diuji di sini adalah BATASNYA, bukan hanya "izinnya ada". Menambah izin
 * `shipments.update-status` ke gudang membuka seluruh jalur ubah status — dan
 * sebagian jalur itu tidak punya pemeriksaan status tujuan sama sekali. Kalau
 * batas tahapnya tidak ditegakkan di semua jalur, gudang justru bisa
 * memindahkan resi ke "pengiriman" atau "diterima", yang bukan wewenangnya.
 *
 * Dua aturan baru yang ikut diuji di sini:
 * - Dalam ranah gudang (bebasDalamTahapGudang), maju dan MUNDUR dibebaskan —
 *   dulu resi yang sudah "Selesai Packing" tidak bisa dikembalikan ke
 *   "Proses Packing" dan tombol update terasa macet.
 * - Gudang kini TIDAK lagi boleh menyentuh resi yang sedang berada di
 *   produksi/kedatangan (di luar rantainya) — kebalikan dari dulu.
 *
 * Catatan penting soal bentuk uji: uji "penolakan" harus memakai tujuan yang
 * HANYA ditolak oleh batas tahap — mis. maju jauh atau ke tahap perjalanan.
 * Bila pilihannya juga ditolak aturan urutan, uji itu lulus karena aturan
 * urutan, bukan karena batas wewenang, dan batasnya bisa dihapus tanpa ada
 * uji yang gagal.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    StatusPengiriman::firstOrCreate(
        ['slug' => 'pemesanan'],
        ['nama' => 'Proses Pemesanan', 'urutan' => 1, 'is_active' => true, 'is_final' => false]
    );
    StatusPengiriman::firstOrCreate(
        ['slug' => 'produksi'],
        ['nama' => 'Proses Produksi', 'urutan' => 2, 'is_active' => true, 'is_final' => false]
    );
    // Dihapus dari alur: hanya dipertahankan agar resi lama tetap punya label.
    // updateOrCreate (bukan firstOrCreate) karena migrasi uji bisa saja sudah
    // membuat barisnya AKTIF — keputusan skill ini harus menang.
    StatusPengiriman::updateOrCreate(
        ['slug' => 'kedatangan'],
        ['nama' => 'Proses Kedatangan/Penurunan', 'urutan' => 3, 'is_active' => false, 'is_final' => false]
    );
    StatusPengiriman::firstOrCreate(
        ['slug' => 'antrian'],
        ['nama' => 'Antrian', 'urutan' => 3, 'is_active' => true, 'is_final' => false]
    );
    StatusPengiriman::firstOrCreate(
        ['slug' => 'packing'],
        ['nama' => 'Proses Packing', 'urutan' => 4, 'is_active' => true, 'is_final' => false]
    );
    StatusPengiriman::firstOrCreate(
        ['slug' => 'selesai-packing'],
        ['nama' => 'Selesai Packing', 'urutan' => 5, 'is_active' => true, 'is_final' => false]
    );
    StatusPengiriman::firstOrCreate(
        ['slug' => 'pengiriman'],
        ['nama' => 'Proses Pengiriman', 'urutan' => 6, 'is_active' => true, 'is_final' => false]
    );
    StatusPengiriman::firstOrCreate(
        ['slug' => 'diterima'],
        ['nama' => 'Diterima Penerima', 'urutan' => 7, 'is_active' => true, 'is_final' => true]
    );
    StatusPengiriman::firstOrCreate(
        ['slug' => 'batal'],
        ['nama' => 'Batal', 'urutan' => 99, 'is_active' => true, 'is_final' => true]
    );
});

function gudangUser(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::WAREHOUSE->value);

    return $user->fresh();
}

function resiStatus(string $slug): Pengiriman
{
    return Pengiriman::factory()->create([
        'no_resi' => 'EQ-2026-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'status_id' => StatusPengiriman::where('slug', $slug)->value('id'),
    ]);
}

function statusId(string $slug): int
{
    return (int) StatusPengiriman::where('slug', $slug)->value('id');
}

it('memberi izin ubah status dan lacak resi kepada gudang', function () {
    $gudang = gudangUser();

    expect($gudang->hasPermissionTo(PermissionEnum::SHIPMENTS_UPDATE_STATUS->value))->toBeTrue()
        ->and($gudang->hasPermissionTo(PermissionEnum::SHIPMENTS_TRACK->value))->toBeTrue();
});

it('membatasi pilihan status gudang ke tahap gudang saja', function () {
    $gudang = gudangUser();

    foreach (['pemesanan', 'antrian', 'packing', 'selesai-packing'] as $slug) {
        expect(PengirimanStageVisibility::bolehPilihStatus($gudang, StatusPengiriman::where('slug', $slug)->first()))
            ->toBeTrue("gudang seharusnya boleh memilih {$slug}");
    }

    foreach (['produksi', 'kedatangan', 'pengiriman', 'diterima', 'batal'] as $slug) {
        expect(PengirimanStageVisibility::bolehPilihStatus($gudang, StatusPengiriman::where('slug', $slug)->first()))
            ->toBeFalse("gudang seharusnya TIDAK boleh memilih {$slug}");
    }
});

it('hanya mengizinkan gudang menyentuh resi yang masih di tahap gudang', function () {
    // Batas per-baris: tahap tujuan boleh sah, tetapi resinya sudah bukan
    // tanggung jawab gudang lagi. Sejak produksi keluar dari rantai gudang,
    // resi yang sedang di produksi juga tidak boleh disentuh gudang.
    $gudang = gudangUser();
    $packing = StatusPengiriman::where('slug', 'packing')->first();

    expect(PengirimanStageVisibility::bolehPindahkan($gudang, resiStatus('pemesanan'), $packing))->toBeTrue()
        ->and(PengirimanStageVisibility::bolehPindahkan($gudang, resiStatus('selesai-packing'), $packing))->toBeTrue()
        ->and(PengirimanStageVisibility::bolehPindahkan($gudang, resiStatus('produksi'), $packing))->toBeFalse()
        ->and(PengirimanStageVisibility::bolehPindahkan($gudang, resiStatus('diterima'), $packing))->toBeFalse()
        ->and(PengirimanStageVisibility::bolehPindahkan($gudang, resiStatus('pengiriman'), $packing))->toBeFalse();
});

it('menawarkan tahap gudang (pemesanan sampai selesai-packing) pada dropdown pemindahan', function () {
    $gudang = gudangUser();

    expect(collect(PengirimanStageVisibility::visibleProgressStatuses($gudang))->pluck('slug')->sort()->values()->all())
        ->toBe(['antrian', 'packing', 'pemesanan', 'selesai-packing']);
});

it('dalam ranah gudang, maju dan mundur dibebaskan', function () {
    // Satu predikat dipakai dua validator + penyusun dropdown. Ini mengunci
    // keputusan pemilik sistem: resi yang salah catat harus bisa dikembalikan
    // tanpa meminta admin.
    $dariPemesanan = StatusPengiriman::where('slug', 'pemesanan')->first();
    $antrian = StatusPengiriman::where('slug', 'antrian')->first();
    $packing = StatusPengiriman::where('slug', 'packing')->first();
    $selesai = StatusPengiriman::where('slug', 'selesai-packing')->first();
    $pengiriman = StatusPengiriman::where('slug', 'pengiriman')->first();

    foreach ([[$antrian, $dariPemesanan], [$dariPemesanan, $antrian], [$packing, $antrian], [$antrian, $packing]] as [$dari, $ke]) {
        expect(PengirimanStageVisibility::bebasDalamTahapGudang($dari, $ke))->toBeTrue();
    }

    expect(PengirimanStageVisibility::bebasDalamTahapGudang($selesai, $pengiriman))->toBeFalse()
        ->and(PengirimanStageVisibility::bebasDalamTahapGudang($pengiriman, $selesai))->toBeFalse();
});

it('gudang tetap melihat semua status aktif pada filter daftar pengiriman', function () {
    // Filter bukan pemindahan: gudang perlu melihat resi lintas status supaya
    // pekerjaannya terlihat. Yang dibatasi adalah pilihannya, bukan filternya.
    $gudang = gudangUser();

    expect(collect(PengirimanStageVisibility::visibleStatuses($gudang))->pluck('slug'))
        ->toContain('pengiriman')
        ->toContain('diterima')
        ->toContain('produksi')
        ->toContain('antrian')
        ->not->toContain('kedatangan');
});

it('mengizinkan gudang memindahkan pemesanan ke antrian lewat ubah status', function () {
    $resi = resiStatus('pemesanan');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('antrian'),
            'catatan' => 'Mushaf sudah siap di gudang',
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('antrian'));
});

it('mengizinkan gudang memajukan antrian ke proses packing', function () {
    $resi = resiStatus('antrian');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('packing'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('packing'));
});

it('mengizinkan gudang mengembalikan selesai-packing ke proses packing (fleksibel)', function () {
    // Inilah keluhan "tombol update belum berfungsi ketika sudah di proses
    // packing": dulu mundur selalu ditolak ("Tidak dapat mengubah status ke
    // tahap sebelumnya") walaupun kedua tahapnya ranah gudang.
    $resi = resiStatus('selesai-packing');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('packing'),
            'catatan' => 'Dikembalikan untuk packing ulang',
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('packing'));
});

it('mengizinkan gudang melompati tahap dalam ranahnya (pemesanan ke packing)', function () {
    // Bebas berarti bebas: tidak dipaksa satu langkah.
    $resi = resiStatus('pemesanan');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('packing'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('packing'));
});

it('menolak gudang memindahkan resi ke pengiriman', function () {
    // "pemesanan" (1) → "pengiriman" (6) SAH menurut aturan urutan (maju),
    // jadi satu-satunya yang menolaknya adalah batas tahap gudang. Kalau batas
    // itu dihapus, uji ini gagal — inilah yang menggigit.
    $resi = resiStatus('pemesanan');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('pengiriman'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('pemesanan'));
});

it('menolak gudang menandai resi diterima penerima', function () {
    $resi = resiStatus('selesai-packing');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('diterima'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('selesai-packing'));
});

it('mengizinkan gudang menandai packing selesai (serah terima ke distribusi)', function () {
    // "packing" (4) → "selesai-packing" (5): tahap pamungkas gudang, titik
    // serah-terima ke distribusi. Distribusi (muatan) menganggap tahap ini
    // "siap dimuat".
    $resi = resiStatus('packing');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('selesai-packing'),
            'catatan' => 'Packing selesai',
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('selesai-packing'));

    // Distribusi melihat datanya: manager hanya boleh melihat tahap akhir, dan
    // `selesai-packing` termasuk di dalamnya.
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole(RoleEnum::MANAGER->value);
    $manager = $manager->fresh();

    expect(PengirimanStageVisibility::isVisible($manager, $resi->fresh()))->toBeTrue();
});

it('menolak gudang membatalkan pengiriman', function () {
    // "batal" selalu sah menurut aturan transisi; yang menolak adalah batas
    // tahap gudang.
    $resi = resiStatus('packing');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('batal'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('packing'));
});

it('menolak gudang menarik kembali resi yang sudah diterima', function () {
    // Resi final: ditolak oleh aturan final DAN batas per-baris. Yang penting
    // datanya tidak berubah.
    $resi = resiStatus('diterima');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('packing'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('diterima'));
});

it('menutup lubang di endpoint batch status', function () {
    // "selesai-packing" (5) → "pengiriman" (6) sah menurut urutan; ditolak
    // hanya karena bukan tahap gudang.
    $resi = resiStatus('selesai-packing');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.batch-status'), [
            'pengiriman_ids' => [$resi->id],
            'status_id' => statusId('pengiriman'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('selesai-packing'));
});

it('menutup lubang di endpoint bulk status', function () {
    // Endpoint bulk memeriksa batas PILIHAN (bolehPilihStatus) sendiri, terpisah
    // dari batas data (applyToQuery). Uji ini memakai manager karena batas
    // pilihannya BERBEDA dari gudang — manager hanya boleh tahap akhir — sehingga
    // yang diuji benar-benar batas tujuan, bukan middleware izin. Gudang kini juga
    // boleh memakai endpoint ini (lihat uji di bawah), jadi memakai gudang di sini
    // tidak lagi membedakan kedua lapisan itu.
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole(RoleEnum::MANAGER->value);
    $manager = $manager->fresh();

    expect($manager->hasPermissionTo(PermissionEnum::SHIPMENTS_BULK_UPDATE->value))->toBeTrue()
        ->and(PengirimanStageVisibility::bolehPilihStatus(
            $manager,
            StatusPengiriman::where('slug', 'produksi')->first()
        ))->toBeFalse();

    $resi = resiStatus('produksi');

    $this->actingAs($manager)
        ->post(route('admin.pengiriman.bulk-status'), [
            'pengiriman_ids' => [$resi->id],
            'status_id' => statusId('packing'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('produksi'));
});

it('menutup lubang di form edit pengiriman', function () {
    // Inilah jalur yang paling mudah luput: form edit hanya diperiksa dengan
    // izin `shipments.update`, yang juga dipegang gudang dan supervisor.
    $resi = resiStatus('selesai-packing');

    $this->actingAs(gudangUser())
        ->put(route('admin.pengiriman.update', $resi), [
            'status_id' => statusId('pengiriman'),
            'alamat_tujuan' => $resi->alamat_tujuan,
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('selesai-packing'));
});

it('menyaring pilihan status di form edit dengan predikat yang sama', function () {
    $resi = resiStatus('pemesanan');

    $props = $this->actingAs(gudangUser())
        ->get(route('admin.pengiriman.edit', $resi))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect(collect($props['statusList'])->pluck('slug')->sort()->values()->all())
        ->toBe(['antrian', 'packing', 'pemesanan', 'selesai-packing']);
});

it('gudang masih boleh menyunting alamat tanpa mengubah status', function () {
    // Menyunting alamat tidak boleh ikut tertutup oleh batas pemindahan status.
    $resi = resiStatus('pemesanan');

    $this->actingAs(gudangUser())
        ->put(route('admin.pengiriman.update', $resi), [
            'status_id' => statusId('pemesanan'),
            'alamat_tujuan' => 'Alamat baru dari gudang',
        ]);

    expect($resi->fresh()->alamat_tujuan)->toBe('Alamat baru dari gudang')
        ->and($resi->fresh()->status_id)->toBe(statusId('pemesanan'));
});

it('tidak mengubah batas kurir maupun manager', function () {
    $kurir = User::factory()->create(['is_active' => true]);
    $kurir->assignRole(RoleEnum::COURIER->value);

    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole(RoleEnum::MANAGER->value);

    expect(collect(PengirimanStageVisibility::visibleProgressStatuses($kurir))->pluck('slug')->sort()->values()->all())
        ->toBe(['batal', 'pengiriman'])
        ->and(collect(PengirimanStageVisibility::visibleProgressStatuses($manager))->pluck('slug')->all())
        ->toEqualCanonicalizing(['selesai-packing', 'pengiriman', 'tiba', 'diterima', 'batal']);
});

it('menolak kurir memakai izinnya untuk tahap gudang', function () {
    // Kurir punya shipments.update-status, tetapi batas tahapnya tetap berlaku.
    // "pemesanan" (1) → "produksi" (2) sah menurut urutan, ditolak hanya karena
    // bukan tahap perjalanan.
    $kurir = User::factory()->create(['is_active' => true]);
    $kurir->assignRole(RoleEnum::COURIER->value);

    $resi = resiStatus('pemesanan');

    $this->actingAs($kurir)
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('produksi'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('pemesanan'));
});

it('memberi gudang izin lacak supaya halaman scan bisa mencari resi', function () {
    $resi = resiStatus('packing');

    $this->actingAs(gudangUser())
        ->getJson('/admin/pengiriman-status/'.$resi->no_resi)
        ->assertSuccessful();
});

it('tidak membatasi super-admin', function () {
    $superAdmin = User::factory()->create(['is_active' => true]);
    $superAdmin->assignRole(RoleEnum::SUPER_ADMIN->value);

    $resi = resiStatus('antrian');

    $this->actingAs($superAdmin)
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('packing'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('packing'));
});

it('menutup rapat bila daftar tahap gudang dikosongkan', function () {
    config(['pengiriman.warehouse_stage_slugs' => []]);

    $gudang = gudangUser();

    expect(PengirimanStageVisibility::bolehPilihStatus($gudang, StatusPengiriman::where('slug', 'antrian')->first()))
        ->toBeFalse()
        ->and(collect(PengirimanStageVisibility::visibleProgressStatuses($gudang)))->toBeEmpty();
});

it('menjaga integritas: slug tahap gudang harus ada di basis data', function () {
    // Penjaga terhadap salah tulis slug yang membuat daftar kosong tanpa terlihat.
    $slugs = StatusPengiriman::pluck('slug')->all();

    foreach (PengirimanStageVisibility::allowedGudangSlugs() as $slug) {
        expect($slugs)->toContain($slug);
    }
});

it('mengizinkan gudang memakai tombol Update Status Massal', function () {
    // Tombol di halaman digerbangi izin yang SAMA dengan rutenya. Dulu rute ini
    // hanya menerima `shipments.bulk-update` — izin yang tidak dipegang satu pun
    // akun gudang — sehingga panel "Update Status Massal" muncul TANPA tombol
    // Update, sementara jalur per-resi justru boleh.
    $gudang = gudangUser();
    $resi = resiStatus('pemesanan');

    expect($gudang->hasPermissionTo(PermissionEnum::SHIPMENTS_UPDATE_STATUS->value))->toBeTrue();

    $this->actingAs($gudang)
        ->postJson(route('admin.pengiriman.bulk-status'), [
            'pengiriman_ids' => [$resi->id],
            'status_id' => statusId('packing'),
            'catatan' => 'bulk oleh gudang',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($resi->fresh()->status_id)->toBe(statusId('packing'));

    $this->assertDatabaseHas('status_histories', [
        'pengiriman_id' => $resi->id,
        'status_to' => statusId('packing'),
        'created_by' => $gudang->id,
    ]);
});

it('tetap menolak gudang memindahkan status di luar tahapnya lewat bulk', function () {
    // Batas tahap berlaku juga di jalur massal: "pengiriman" sah menurut urutan
    // (maju dari pemesanan), ditolak hanya karena bukan wewenang gudang.
    $gudang = gudangUser();
    $resi = resiStatus('pemesanan');

    $this->actingAs($gudang)
        ->postJson(route('admin.pengiriman.bulk-status'), [
            'pengiriman_ids' => [$resi->id],
            'status_id' => statusId('pengiriman'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('pemesanan'));
    $this->assertDatabaseCount('status_histories', 0);
});

it('tidak memberi gudang wewenang set alamat massal', function () {
    // Set alamat massal menautkan permintaan mushaf dan menandainya diproses —
    // bukan pekerjaan gudang. Rutenya tetap hanya `shipments.bulk-update`, dan
    // tombolnya di halaman kini ikut disembunyikan (dulu digerbangi
    // shipments.update sehingga tampil padahal server selalu menolak).
    $gudang = gudangUser();
    $resi = resiStatus('pemesanan');

    $this->actingAs($gudang)
        ->postJson(route('admin.pengiriman.bulk-alamat'), [
            'pengiriman_ids' => [$resi->id],
            'alamat_tujuan' => 'Alamat uji gudang',
        ])
        ->assertForbidden();
});

it('tidak lagi memberi gudang izin halaman donatur', function () {
    // Keputusan pemilik sistem: halaman Donatur tidak diperlukan di sisi gudang.
    // Tanpa izin ini menu "Kelola Donatur" hilang dari navigasi SEKALIGUS
    // rutenya ditolak server — bukan sekadar disembunyikan.
    $gudang = gudangUser();

    expect($gudang->hasPermissionTo(PermissionEnum::DONATUR_READ->value))->toBeFalse();

    $this->actingAs($gudang)
        ->get(route('admin.donatur.index'))
        ->assertForbidden();
});

it('mengirim izin yang dibutuhkan tombol bulk ke halaman pengiriman', function () {
    $gudang = gudangUser();

    $izin = $this->actingAs($gudang)
        ->get(route('admin.pengiriman.index'))
        ->assertSuccessful()
        ->viewData('page')['props']['auth']['user']['permissions'];

    expect($izin)->toContain(PermissionEnum::SHIPMENTS_UPDATE_STATUS->value)
        ->and($izin)->not->toContain(PermissionEnum::SHIPMENTS_BULK_UPDATE->value)
        ->and($izin)->not->toContain(PermissionEnum::DONATUR_READ->value);
});
