<?php

use App\Enums\RoleEnum;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\StokMushaf;
use App\Models\StokMushafPergerakan;
use App\Models\TransferStok;
use App\Models\User;
use App\Services\StokMushafService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Fase 3 rombakan gudang: stok mushaf TAMPA MUWAKIF + dokumen transfer antar
 * gudang.
 *
 * Keputusan pemilik sistem yang dijaga di sini:
 * - stok diinput manual manager/SPV ("N dus jenis X di gudang Y"), dihitung
 *   dalam DUS (1 dus A5 = 20 eks), dipotong saat muwakif dilampirkan;
 * - transfer lewat DOKUMEN: stok BARU berpindah saat diterima SPV gudang
 *   TUJUAN (barang fisik sudah tiba), bukan saat dokumen dibuat;
 * - SPV asal TIDAK boleh menerima transfernya sendiri;
 * - freelancer (role warehouse) hanya melihat;
 * - seluruh pergerakan tercatat di buku besar beserta pelakunya.
 *
 * Temuan lapangan 19/10/2026: A5/A6 punya varian BER-TERJEMAHAN dan TANPA terjemahan.
 * Varian WAJIB diisi di tiap pergerakan/saldo — A5 ber-terjemahan dan A5 tanpa terjemahan
 * adalah dua tumpukan terpisah, bukan satu angka gabungan.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function stokGudang(string $kode): Gudang
{
    return Gudang::byKode($kode);
}

function stokJenisA5(): JenisQuran
{
    $jenis = JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create();
    $jenis->update(['default_capacity' => 20]);

    return $jenis->refresh();
}

function stokAkun(string $role, ?Gudang $gudang = null): User
{
    $user = User::factory()->create(['is_active' => true, 'gudang_id' => $gudang?->id]);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function stokIsiAwal(Gudang $gudang, JenisQuran $jenis, int $eks, bool $terjemahan = false): void
{
    // Setup data lewat jalur yang sama dengan produksi (service), bukan HTTP.
    app(StokMushafService::class)->catatMasuk(
        $gudang,
        $jenis,
        $eks,
        $terjemahan,
        'Isi awal uji',
        stokAkun(RoleEnum::MANAGER_REPACKING->value),
    );
}

it('mencatat stok masuk berikut buku besarnya, dalam dus yang dikonversi ke eks', function () {
    $gudang = stokGudang('SMG');
    $jenis = stokJenisA5();
    $pelaku = stokAkun(RoleEnum::SPV_GUDANG->value, $gudang);

    $respons = $this->actingAs($pelaku)
        ->postJson('/admin/warehouse/stok/entri', [
            'gudang_id' => $gudang->id,
            'jenis_quran_id' => $jenis->id,
            'tipe' => 'masuk',
            'terjemahan' => 1,
            'jumlah_eks' => 60,
            'catatan' => 'Terima barang produksi',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    // 3 dus A5 = 60 eks, tercatat sebagai varian BER-terjemahan.
    expect(DB::table('stok_mushaf')->where('gudang_id', $gudang->id)->value('jumlah_eks'))->toBe(60)
        ->and($respons->json('data.eks'))->toBe(60)
        ->and($respons->json('data.dus'))->toBe(3)
        ->and($respons->json('message'))->toContain('ada terjemahan');

    $pergerakan = StokMushafPergerakan::first();
    expect($pergerakan->tipe)->toBe('masuk')
        ->and($pergerakan->jumlah_eks)->toBe(60)
        ->and($pergerakan->terjemahan)->toBeTrue()
        ->and($pergerakan->created_by)->toBe($pelaku->id)
        ->and($pergerakan->catatan)->toBe('Terima barang produksi');
});

it('mengurangi stok saat muwakif dilampirkan dan menolak bila stok tidak cukup', function () {
    $gudang = stokGudang('BTL');
    $jenis = stokJenisA5();
    $pelaku = stokAkun(RoleEnum::SPV_GUDANG->value, $gudang);

    stokIsiAwal($gudang, $jenis, 40);

    $this->actingAs($pelaku)
        ->postJson('/admin/warehouse/stok/entri', [
            'gudang_id' => $gudang->id,
            'jenis_quran_id' => $jenis->id,
            'tipe' => 'keluar',
            'terjemahan' => 0,
            'jumlah_eks' => 12,
            'catatan' => 'Dilampirkan ke muwakif',
        ]);

    expect(DB::table('stok_mushaf')->where('gudang_id', $gudang->id)->value('jumlah_eks'))->toBe(28);

    $gagal = $this->actingAs($pelaku)
        ->postJson('/admin/warehouse/stok/entri', [
            'gudang_id' => $gudang->id,
            'jenis_quran_id' => $jenis->id,
            'tipe' => 'keluar',
            'terjemahan' => 0,
            'jumlah_eks' => 500,
        ]);

    $gagal->assertStatus(422)
        ->assertJsonPath('success', false);

    // Pesan galat menyebut variannya: stok terjemahan dan non-terjemahan terpisah.
    expect($gagal->json('message'))->toContain('Stok tidak cukup')
        ->and($gagal->json('message'))->toContain('tanpa terjemahan');

    // Stok tidak boleh minus.
    expect(DB::table('stok_mushaf')->where('gudang_id', $gudang->id)->value('jumlah_eks'))->toBe(28);
});

it('menolak SPV mencatat pergerakan stok gudang lain', function () {
    $semarang = stokGudang('SMG');
    $boyolali = stokGudang('BTL');
    $jenis = stokJenisA5();

    $spvSmg = stokAkun(RoleEnum::SPV_GUDANG->value, $semarang);

    $this->actingAs($spvSmg)
        ->postJson('/admin/warehouse/stok/entri', [
            'gudang_id' => $boyolali->id,
            'jenis_quran_id' => $jenis->id,
            'tipe' => 'masuk',
            'terjemahan' => 0,
            'jumlah_eks' => 10,
        ])
        ->assertForbidden()
        ->assertJsonPath('success', false);

    expect(StokMushaf::where('gudang_id', $boyolali->id)->exists())->toBeFalse();
});

it('menolak SPV membuat dokumen transfer dari gudang lain', function () {
    $semarang = stokGudang('SMG');
    $boyolali = stokGudang('BTL');
    $jenis = stokJenisA5();
    stokIsiAwal($boyolali, $jenis, 20);

    $this->actingAs(stokAkun(RoleEnum::SPV_GUDANG->value, $semarang))
        ->postJson('/admin/warehouse/stok/transfer', [
            'gudang_asal_id' => $boyolali->id,
            'gudang_tujuan_id' => $semarang->id,
            'jenis_quran_id' => $jenis->id,
            'terjemahan' => 0,
            'jumlah_eks' => 5,
        ])
        ->assertForbidden();

    expect(TransferStok::count())->toBe(0);
});

it('membuat dokumen transfer tanpa menggerakkan stok dulu', function () {
    $asal = stokGudang('SMG');
    $tujuan = stokGudang('BTL');
    $jenis = stokJenisA5();
    stokIsiAwal($asal, $jenis, 80, true);

    $respons = $this->actingAs(stokAkun(RoleEnum::MANAGER_REPACKING->value))
        ->postJson('/admin/warehouse/stok/transfer', [
            'gudang_asal_id' => $asal->id,
            'gudang_tujuan_id' => $tujuan->id,
            'jenis_quran_id' => $jenis->id,
            'terjemahan' => 1,
            'jumlah_eks' => 40,
        ])
        ->assertSuccessful();

    $dokumen = TransferStok::first();
    expect($dokumen->status)->toBe('diproses')
        ->and($dokumen->jumlah_eks)->toBe(40)
        // Varian ikut di dokumennya: yang dipindah adalah A5 ber-terjemahan.
        ->and($dokumen->terjemahan)->toBeTrue()
        ->and($dokumen->kode)->toBe('TS-'.today()->format('Ymd').'-001')
        // Stok asal BELUM berpindah — menunggu SPV tujuan menerima.
        ->and(DB::table('stok_mushaf')->where('gudang_id', $asal->id)->value('jumlah_eks'))->toBe(80)
        ->and(DB::table('stok_mushaf')->where('gudang_id', $tujuan->id)->count())->toBe(0);

    expect($respons->json('message'))->toContain('SPV tujuan menerima');
});

it('menolak dokumen yang melebihi stok asal atau asal sama dengan tujuan', function () {
    $asal = stokGudang('SMG');
    $tujuan = stokGudang('BTL');
    $jenis = stokJenisA5();
    stokIsiAwal($asal, $jenis, 20);

    $melebihi = $this->actingAs(stokAkun(RoleEnum::MANAGER_REPACKING->value))
        ->postJson('/admin/warehouse/stok/transfer', [
            'gudang_asal_id' => $asal->id,
            'gudang_tujuan_id' => $tujuan->id,
            'jenis_quran_id' => $jenis->id,
            'terjemahan' => 0,
            'jumlah_eks' => 180,
        ]);

    $melebihi->assertStatus(422)
        ->assertJsonPath('success', false);

    expect($melebihi->json('message'))->toContain('Stok tidak cukup');

    $this->actingAs(stokAkun(RoleEnum::MANAGER_REPACKING->value))
        ->postJson('/admin/warehouse/stok/transfer', [
            'gudang_asal_id' => $asal->id,
            'gudang_tujuan_id' => $asal->id,
            'jenis_quran_id' => $jenis->id,
            'terjemahan' => 0,
            'jumlah_eks' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('gudang_asal_id');
});

it('memindahkan stok saat diterima SPV gudang tujuan, dan hanya dia', function () {
    $asal = stokGudang('SMG');
    $tujuan = stokGudang('BTL');
    $jenis = stokJenisA5();
    stokIsiAwal($asal, $jenis, 80);

    $spvAsal = stokAkun(RoleEnum::SPV_GUDANG->value, $asal);
    $spvTujuan = stokAkun(RoleEnum::SPV_GUDANG->value, $tujuan);

    // PJ (manager-repacking tanpa terikat gudang) yang membuat dokumen — SPV
    // tercakup hanya boleh membuat DARI gudangnya (denguji terpisah).
    $this->actingAs(stokAkun(RoleEnum::MANAGER_REPACKING->value))
        ->postJson('/admin/warehouse/stok/transfer', [
            'gudang_asal_id' => $asal->id,
            'gudang_tujuan_id' => $tujuan->id,
            'jenis_quran_id' => $jenis->id,
            'terjemahan' => 0,
            'jumlah_eks' => 40,
            'catatan' => 'Stok Boyolali habis',
        ]);

    $dokumen = TransferStok::first();

    // SPV ASAL tidak boleh menerima sendiri — yang menandai barang tiba adalah
    // pihak tujuan.
    $this->actingAs($spvAsal)
        ->postJson("/admin/warehouse/stok/transfer/{$dokumen->id}/terima")
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    $respons = $this->actingAs($spvTujuan)
        ->postJson("/admin/warehouse/stok/transfer/{$dokumen->id}/terima")
        ->assertSuccessful();

    $dokumen->refresh();

    expect(DB::table('stok_mushaf')->where('gudang_id', $asal->id)->value('jumlah_eks'))->toBe(40)
        ->and(DB::table('stok_mushaf')->where('gudang_id', $tujuan->id)->value('jumlah_eks'))->toBe(40)
        ->and($dokumen->status)->toBe('diterima')
        ->and($dokumen->approved_by)->toBe($spvTujuan->id);

    // Buku besar: keluar dari asal, masuk ke tujuan — dua-duanya tercatat.
    expect(StokMushafPergerakan::where('tipe', 'transfer_keluar')->count())->toBe(1)
        ->and(StokMushafPergerakan::where('tipe', 'transfer_masuk')->count())->toBe(1)
        ->and($respons->json('message'))->toContain('berkurang');
});

it('menolak menerima dokumen yang sudah diterima dan membiarkannya dibatalkan sebelum itu', function () {
    $asal = stokGudang('SMG');
    $tujuan = stokGudang('BTL');
    $jenis = stokJenisA5();
    $pembuat = stokAkun(RoleEnum::MANAGER_REPACKING->value);
    stokIsiAwal($asal, $jenis, 40);

    $this->actingAs($pembuat)->postJson('/admin/warehouse/stok/transfer', [
        'gudang_asal_id' => $asal->id,
        'gudang_tujuan_id' => $tujuan->id,
        'jenis_quran_id' => $jenis->id,
        'terjemahan' => 0,
        'jumlah_eks' => 40,
    ]);
    $dokumen = TransferStok::first();

    // Pembuat boleh membatalkan selama masih diproses — stok tak berubah.
    $this->actingAs($pembuat)
        ->postJson("/admin/warehouse/stok/transfer/{$dokumen->id}/batalkan")
        ->assertSuccessful();

    expect($dokumen->fresh()->status)->toBe('dibatalkan')
        ->and(DB::table('stok_mushaf')->where('gudang_id', $asal->id)->value('jumlah_eks'))->toBe(40);

    $spvTujuan = stokAkun(RoleEnum::SPV_GUDANG->value, $tujuan);

    $this->actingAs($spvTujuan)
        ->postJson("/admin/warehouse/stok/transfer/{$dokumen->id}/terima")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(DB::table('stok_mushaf')->where('gudang_id', $tujuan->id)->count())->toBe(0);
});

it('memberi freelancer akses baca saja', function () {
    $freelancer = stokAkun(RoleEnum::WAREHOUSE->value);

    $this->actingAs($freelancer)
        ->get('/admin/warehouse/stok')
        ->assertSuccessful();

    $this->actingAs($freelancer)
        ->postJson('/admin/warehouse/stok/entri', [
            'gudang_id' => stokGudang('SMG')->id,
            'jenis_quran_id' => stokJenisA5()->id,
            'tipe' => 'masuk',
            'terjemahan' => 0,
            'jumlah_eks' => 1,
        ])
        ->assertForbidden();

    expect(StokMushaf::count())->toBe(0);
});

it('memvalidasi bentuk permintaan entri dan transfer', function () {
    $pengelola = stokAkun(RoleEnum::MANAGER_REPACKING->value);

    $this->actingAs($pengelola)
        ->postJson('/admin/warehouse/stok/entri', ['tipe' => 'masuk'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['gudang_id', 'jenis_quran_id', 'terjemahan', 'jumlah_eks']);

    $this->actingAs($pengelola)
        ->postJson('/admin/warehouse/stok/entri', [
            'gudang_id' => stokGudang('SMG')->id,
            'jenis_quran_id' => stokJenisA5()->id,
            'tipe' => 'diangkut',
            'terjemahan' => 0,
            'jumlah_eks' => 2,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('tipe');

    $this->actingAs($pengelola)
        ->postJson('/admin/warehouse/stok/transfer', [
            'gudang_asal_id' => stokGudang('SMG')->id,
            'gudang_tujuan_id' => stokGudang('BTL')->id,
            'jenis_quran_id' => stokJenisA5()->id,
            'terjemahan' => 0,
            'jumlah_eks' => 0,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('jumlah_eks');
});

it('memisahkan stok A5 ber-terjemahan dan tanpa terjemahan, dan menolak pergerakan tanpa varian', function () {
    $gudang = stokGudang('SMG');
    $jenis = stokJenisA5();
    $pelaku = stokAkun(RoleEnum::SPV_GUDANG->value, $gudang);

    $this->actingAs($pelaku)
        ->postJson('/admin/warehouse/stok/entri', [
            'gudang_id' => $gudang->id,
            'jenis_quran_id' => $jenis->id,
            'tipe' => 'masuk',
            'terjemahan' => 1,
            'jumlah_eks' => 20,
        ])
        ->assertSuccessful();

    $this->actingAs($pelaku)
        ->postJson('/admin/warehouse/stok/entri', [
            'gudang_id' => $gudang->id,
            'jenis_quran_id' => $jenis->id,
            'tipe' => 'masuk',
            'terjemahan' => 0,
            'jumlah_eks' => 60,
        ])
        ->assertSuccessful();

    // Dua tumpukan, bukan satu angka 80: 20 eks ber-terjemahan + 60 eks tanpa.
    $saldo = StokMushaf::where('gudang_id', $gudang->id)->orderBy('id')->get();
    expect($saldo)->toHaveCount(2)
        ->and($saldo[0]->terjemahan)->toBeTrue()
        ->and($saldo[0]->jumlah_eks)->toBe(20)
        ->and($saldo[1]->terjemahan)->toBeFalse()
        ->and($saldo[1]->jumlah_eks)->toBe(60);

    // Mengurangi 20 eks TANPA terjemahan tidak boleh memakan tumpukan ber-terjemahan.
    $this->actingAs($pelaku)
        ->postJson('/admin/warehouse/stok/entri', [
            'gudang_id' => $gudang->id,
            'jenis_quran_id' => $jenis->id,
            'tipe' => 'keluar',
            'terjemahan' => 0,
            'jumlah_eks' => 20,
        ])
        ->assertSuccessful();

    expect(StokMushaf::where('gudang_id', $gudang->id)->where('terjemahan', true)->value('jumlah_eks'))->toBe(20)
        ->and(StokMushaf::where('gudang_id', $gudang->id)->where('terjemahan', false)->value('jumlah_eks'))->toBe(40);

    // Varian wajib: permintaan tanpa `terjemahan` ditolak, stok tidak berubah.
    $this->actingAs($pelaku)
        ->postJson('/admin/warehouse/stok/entri', [
            'gudang_id' => $gudang->id,
            'jenis_quran_id' => $jenis->id,
            'tipe' => 'masuk',
            'jumlah_eks' => 20,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('terjemahan');

    expect((int) StokMushaf::where('gudang_id', $gudang->id)->sum('jumlah_eks'))->toBe(60);
});

it('menyampaikan galat validasi sebagai JSON, bukan pengalihan halaman', function () {
    // Halaman stok memanggil endpoint ini dengan fetch + Accept: application/json.
    // Tanpa header itu Laravel menjawab REDIRECT 302 dan browser menerima HTML —
    // pesan aslinya tidak pernah sampai ke layar (kejadian 19/10/2026).
    $pengelola = stokAkun(RoleEnum::MANAGER_REPACKING->value);

    $respons = $this->actingAs($pengelola)->postJson('/admin/warehouse/stok/entri', [
        'gudang_id' => stokGudang('SMG')->id,
        'jenis_quran_id' => stokJenisA5()->id,
        'tipe' => 'masuk',
        'jumlah_eks' => 1,
    ]);

    $respons->assertStatus(422)
        ->assertHeader('content-type', 'application/json')
        ->assertJsonValidationErrors('terjemahan');
});

it('mengirim props datar yang benar-benar dipakai halaman stok', function () {
    // Halaman membaca `stok.gudang` / `stok.jenis` sebagai TEKS. Dulu halaman membaca
    // `stok.gudang?.nama`, sehingga kolom Gudang dan Jenis tampil kosong walau stoknya ada.
    $gudang = stokGudang('SMG');
    $jenis = stokJenisA5();
    stokIsiAwal($gudang, $jenis, 20, true);

    $props = $this->actingAs(stokAkun(RoleEnum::MANAGER_REPACKING->value))
        ->get('/admin/warehouse/stok')
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($props['stokList'][0])->toMatchArray([
        'gudang' => $gudang->nama,
        'jenis' => $jenis->nama_jenis,
        'terjemahan' => 'Ada terjemahan',
        'eks' => 20,
    ])
        ->and($props['stokList'][0])->not->toHaveKey('jenisQuran')
        ->and($props['pergerakanList'][0]['terjemahan'])->toBe('Ada terjemahan');
});

/**
 * Race condition: penjaga status dulu membaca instance hasil route binding di
 * luar transaksi. Dua request bersamaan sama-sama membawa instance berstatus
 * 'diproses', jadi keduanya lolos. Ujian di bawah meniru request kedua dengan
 * instance basi yang dimuat SEBELUM request pertama selesai.
 */
it('tidak memindahkan stok dua kali saat transfer diterima bersamaan', function () {
    $asal = stokGudang('SMG');
    $tujuan = stokGudang('BTL');
    $jenis = stokJenisA5();
    stokIsiAwal($asal, $jenis, 200);

    $layanan = app(StokMushafService::class);
    $manager = stokAkun(RoleEnum::MANAGER_REPACKING->value);
    $dokumen = $layanan->buatTransfer($asal, $tujuan, $jenis, 60, false, null, $manager);

    $basi = TransferStok::find($dokumen->id);

    $layanan->terimaTransfer($dokumen, $manager);

    expect(fn () => $layanan->terimaTransfer($basi, $manager))
        ->toThrow(Exception::class, 'sudah diterima');

    expect(DB::table('stok_mushaf')->where('gudang_id', $asal->id)->value('jumlah_eks'))->toBe(140)
        ->and(DB::table('stok_mushaf')->where('gudang_id', $tujuan->id)->value('jumlah_eks'))->toBe(60)
        ->and(StokMushafPergerakan::where('transfer_id', $dokumen->id)->count())->toBe(2);
});

it('tidak membatalkan dokumen yang baru saja diterima', function () {
    $asal = stokGudang('SMG');
    $tujuan = stokGudang('BTL');
    $jenis = stokJenisA5();
    stokIsiAwal($asal, $jenis, 100);

    $layanan = app(StokMushafService::class);
    $manager = stokAkun(RoleEnum::MANAGER_REPACKING->value);
    $dokumen = $layanan->buatTransfer($asal, $tujuan, $jenis, 40, false, null, $manager);

    $basi = TransferStok::find($dokumen->id);

    $layanan->terimaTransfer($dokumen, $manager);

    expect(fn () => $layanan->batalkanTransfer($basi, $manager))
        ->toThrow(Exception::class, 'masih "diproses"');

    // Stok sudah pindah, jadi dokumennya harus tetap tercatat diterima.
    expect($dokumen->fresh()->status)->toBe('diterima')
        ->and(DB::table('stok_mushaf')->where('gudang_id', $tujuan->id)->value('jumlah_eks'))->toBe(40);
});

it('tidak menerima dokumen yang baru saja dibatalkan', function () {
    $asal = stokGudang('SMG');
    $tujuan = stokGudang('BTL');
    $jenis = stokJenisA5();
    stokIsiAwal($asal, $jenis, 100);

    $layanan = app(StokMushafService::class);
    $manager = stokAkun(RoleEnum::MANAGER_REPACKING->value);
    $dokumen = $layanan->buatTransfer($asal, $tujuan, $jenis, 40, false, null, $manager);

    $basi = TransferStok::find($dokumen->id);

    $layanan->batalkanTransfer($dokumen, $manager);

    expect(fn () => $layanan->terimaTransfer($basi, $manager))
        ->toThrow(Exception::class, 'sudah dibatalkan');

    expect(DB::table('stok_mushaf')->where('gudang_id', $asal->id)->value('jumlah_eks'))->toBe(100)
        ->and(DB::table('stok_mushaf')->where('gudang_id', $tujuan->id)->value('jumlah_eks'))->toBeIn([null, 0]);
});
