<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Muatan;
use App\Models\MuatanItem;
use App\Models\MushafRequest;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\SerahTerimaKurir;
use App\Models\StatusPengiriman;
use App\Models\TrackingHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * Serah terima kurir (16/10/2026) — pindai TERAKHIR sebelum mushaf berpindah tangan:
 *   - di tujuan: kerdus atau per mushaf → "Tiba di Tujuan"; "Diterima" oleh distribusi;
 *   - di tengah jalan: per mushaf → resi dialihkan ke penerima baru, lepas dari permintaan.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([PermissionEnum::MUATAN_READ, PermissionEnum::MUATAN_SCAN, PermissionEnum::MUATAN_COMPLETE] as $izin) {
        Permission::firstOrCreate(['name' => $izin->value, 'guard_name' => 'web']);
    }

    Role::firstOrCreate(['name' => RoleEnum::COURIER->value], ['display_name' => 'Kurir', 'guard_name' => 'web'])
        ->syncPermissions([PermissionEnum::MUATAN_READ->value, PermissionEnum::MUATAN_SCAN->value]);
    Role::firstOrCreate(['name' => RoleEnum::DISTRIBUSI->value], ['display_name' => 'Distribusi', 'guard_name' => 'web'])
        ->syncPermissions([PermissionEnum::MUATAN_READ->value, PermissionEnum::MUATAN_COMPLETE->value]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['selesai-packing' => 5, 'pengiriman' => 6, 'tiba' => 7, 'diterima' => 8] as $slug => $urutan) {
        StatusPengiriman::updateOrCreate(
            ['slug' => $slug],
            ['nama' => $slug === 'tiba' ? 'Tiba di Tujuan' : ucfirst(str_replace('-', ' ', $slug)), 'urutan' => $urutan, 'is_active' => true, 'is_final' => $slug === 'diterima']
        );
    }

    Storage::fake('public');
});

function stPengguna(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);

    return $user;
}

function stSlug(string $slug): int
{
    return (int) StatusPengiriman::where('slug', $slug)->value('id');
}

/**
 * Resi di muatan kurir, berstatus "pengiriman", untuk satu lembaga.
 *
 * @return array<int, Pengiriman>
 */
function stResiDiMuatan(Muatan $muatan, int $jumlah, array $atribut = []): array
{
    $resi = [];

    for ($i = 0; $i < $jumlah; $i++) {
        $p = Pengiriman::factory()->create($atribut + [
            'status_id' => stSlug('pengiriman'),
            'nama_lembaga' => 'TPQ Al-Ikhlas',
            'alamat_tujuan' => 'Jl. Melati 1, Semarang',
            'nama_penerima' => 'Ustadz Ahmad',
            'jumlah_quran' => 1,
        ]);
        MuatanItem::factory()->create(['muatan_id' => $muatan->id, 'pengiriman_id' => $p->id]);
        $resi[] = $p;
    }

    return $resi;
}

/**
 * @param  array<int, Pengiriman>  $resi
 */
function stKerdus(string $kode, array $resi): PackingBox
{
    $box = PackingBox::factory()->create([
        'kode_kerdus' => $kode,
        'daily_packing_task_id' => DailyPackingTask::factory()->create()->id,
    ]);

    // urutan_dalam_box diisi berurutan: factory memilih angka acak yang sesekali bentrok
    // dengan kunci unik (packing_box_id, urutan_dalam_box) — sumber tes yang kadang gagal.
    foreach (array_values($resi) as $i => $p) {
        PackingItem::factory()->create(['packing_box_id' => $box->id, 'pengiriman_id' => $p->id, 'urutan_dalam_box' => $i + 1]);
    }

    return $box;
}

it('kurir memindai kerdus di tujuan lalu seluruh isinya menjadi Tiba di Tujuan', function () {
    $kurir = stPengguna(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $resi = stResiDiMuatan($muatan, 3);
    stKerdus('KB-20261016-001-A5-01', $resi);

    $periksa = $this->actingAs($kurir)
        ->postJson(route('admin.kurir.serah-terima.periksa'), ['kode' => 'KB-20261016-001-A5-01', 'mode' => 'tujuan'])
        ->assertSuccessful()
        ->assertJsonPath('kode_kerdus', 'KB-20261016-001-A5-01')
        ->assertJsonPath('jumlah_boleh', 3)
        ->assertJsonCount(1, 'tujuan');

    // Memeriksa tidak boleh menulis apa pun.
    expect(SerahTerimaKurir::count())->toBe(0)
        ->and($resi[0]->fresh()->status_id)->toBe(stSlug('pengiriman'));

    $this->actingAs($kurir)
        ->post(route('admin.kurir.serah-terima.simpan'), [
            'mode' => 'tujuan',
            'pengiriman_ids' => collect($periksa->json('resi'))->pluck('id')->all(),
            'kode_kerdus' => 'KB-20261016-001-A5-01',
            'nama_penerima' => 'Bu Siti (pengurus TPQ)',
            'no_hp_penerima' => '081234567890',
            'latitude' => -6.9932,
            'longitude' => 110.4203,
            'foto' => [UploadedFile::fake()->image('serah.jpg')],
        ], ['Accept' => 'application/json'])
        ->assertSuccessful()
        ->assertJsonPath('jumlah_resi', 3);

    foreach ($resi as $p) {
        $segar = $p->fresh();
        expect($segar->status_id)->toBe(stSlug('tiba'))
            ->and($segar->received_by)->toBe('Bu Siti (pengurus TPQ)')
            ->and($segar->nama_lembaga)->toBe('TPQ Al-Ikhlas'); // tujuan tidak berubah
    }

    $catatan = SerahTerimaKurir::where('pengiriman_id', $resi[0]->id)->sole();
    expect($catatan->jenis)->toBe('tujuan')
        ->and($catatan->kode_kerdus)->toBe('KB-20261016-001-A5-01')
        ->and($catatan->muatan_id)->toBe($muatan->id)
        ->and($catatan->user_id)->toBe($kurir->id)
        ->and($catatan->foto)->toHaveCount(1);

    Storage::disk('public')->assertExists($catatan->foto[0]);

    // Riwayat tracking (yang tampil di halaman lacak publik) ikut tercatat dengan GPS.
    $jejak = TrackingHistory::where('pengiriman_id', $resi[0]->id)->sole();
    expect($jejak->status_id)->toBe(stSlug('tiba'))
        ->and($jejak->keterangan)->toContain('Bu Siti')
        ->and((float) $jejak->latitude)->toBe(-6.9932);
});

it('kurir boleh menyerahkan per mushaf di tujuan dengan memindai QR resi', function () {
    $kurir = stPengguna(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    [$p] = stResiDiMuatan($muatan, 1);

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.serah-terima.periksa'), ['kode' => json_encode(['no_resi' => $p->no_resi]), 'mode' => 'tujuan'])
        ->assertSuccessful()
        ->assertJsonPath('resi.0.no_resi', $p->no_resi)
        ->assertJsonPath('resi.0.boleh', true);

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.serah-terima.simpan'), ['mode' => 'tujuan', 'pengiriman_ids' => [$p->id], 'nama_penerima' => 'Pak RT'])
        ->assertSuccessful();

    expect($p->fresh()->status_id)->toBe(stSlug('tiba'));
});

it('penyerahan di tengah jalan mengalihkan resi dan melepasnya dari permintaan asal', function () {
    $kurir = stPengguna(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $permintaan = MushafRequest::factory()->create(['status' => 'processed']);
    $resi = stResiDiMuatan($muatan, 5, ['mushaf_request_id' => $permintaan->id]);
    $permintaan->update(['pengiriman_id' => $resi[0]->id]);

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.serah-terima.simpan'), [
            'mode' => 'tengah_jalan',
            'pengiriman_ids' => [$resi[0]->id, $resi[1]->id],
            'nama_penerima' => 'Takmir Masjid Nurul Huda',
            'no_hp_penerima' => '089876543210',
            'alamat_penerima' => 'Jl. Raya Ungaran km 3',
            'catatan' => 'Diminta di perjalanan',
        ])
        ->assertSuccessful()
        ->assertJsonPath('jumlah_resi', 2);

    $dialihkan = $resi[0]->fresh();
    expect($dialihkan->status_id)->toBe(stSlug('tiba'))
        ->and($dialihkan->nama_penerima)->toBe('Takmir Masjid Nurul Huda')
        ->and($dialihkan->alamat_tujuan)->toBe('Jl. Raya Ungaran km 3')
        ->and($dialihkan->no_hp_penerima)->toBe('089876543210')
        ->and($dialihkan->nama_lembaga)->toBeNull()
        ->and($dialihkan->mushaf_request_id)->toBeNull();

    // Asal disalin ke catatan serah terima — resinya sudah tidak menyimpannya lagi.
    $catatan = SerahTerimaKurir::where('pengiriman_id', $resi[0]->id)->sole();
    expect($catatan->jenis)->toBe('tengah_jalan')
        ->and($catatan->mushaf_request_id_asal)->toBe($permintaan->id)
        ->and($catatan->nama_lembaga_asal)->toBe('TPQ Al-Ikhlas')
        ->and($catatan->alamat_asal)->toBe('Jl. Melati 1, Semarang');

    // Lembaga kini menunggu 3 resi; penunjuk lama dipindah ke resi yang masih miliknya.
    $permintaan->refresh();
    expect($permintaan->pengirimans()->count())->toBe(3)
        ->and($permintaan->serahTerimaTengahJalan()->count())->toBe(2)
        ->and($permintaan->pengiriman_id)->toBe($resi[2]->id)
        ->and($permintaan->status)->toBe('processed');

    // Sisa resi tetap untuk lembaga.
    expect($resi[2]->fresh()->mushaf_request_id)->toBe($permintaan->id)
        ->and($resi[2]->fresh()->status_id)->toBe(stSlug('pengiriman'));
});

it('permintaan selesai bila sisa kirimannya sudah diterima saat resi terakhir dialihkan', function () {
    $kurir = stPengguna(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $permintaan = MushafRequest::factory()->create(['status' => 'processed']);
    $resi = stResiDiMuatan($muatan, 2, ['mushaf_request_id' => $permintaan->id]);

    tibakan($resi[0]);
    $resi[0]->update(['status_id' => stSlug('diterima')]);
    expect($permintaan->fresh()->status)->toBe('processed');

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.serah-terima.simpan'), [
            'mode' => 'tengah_jalan',
            'pengiriman_ids' => [$resi[1]->id],
            'nama_penerima' => 'Warga',
            'no_hp_penerima' => '0811',
            'alamat_penerima' => 'Desa Lerep',
        ])
        ->assertSuccessful();

    expect($permintaan->fresh()->status)->toBe('completed');
});

it('permintaan selesai bila SELURUH resinya diserahkan di tengah jalan', function () {
    $kurir = stPengguna(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $permintaan = MushafRequest::factory()->create(['status' => 'processed']);
    $resi = stResiDiMuatan($muatan, 2, ['mushaf_request_id' => $permintaan->id]);

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.serah-terima.simpan'), [
            'mode' => 'tengah_jalan',
            'pengiriman_ids' => collect($resi)->pluck('id')->all(),
            'nama_penerima' => 'Warga',
            'no_hp_penerima' => '0811',
            'alamat_penerima' => 'Desa Lerep',
        ])
        ->assertSuccessful();

    expect($permintaan->fresh()->status)->toBe('completed');
});

it('menolak kerdus untuk penyerahan di tengah jalan', function () {
    $kurir = stPengguna(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    stKerdus('KB-20261016-002-A5-01', stResiDiMuatan($muatan, 2));

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.serah-terima.periksa'), ['kode' => 'KB-20261016-002-A5-01', 'mode' => 'tengah_jalan'])
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});

it('mewajibkan no HP dan alamat penerima untuk penyerahan di tengah jalan', function () {
    $kurir = stPengguna(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    [$p] = stResiDiMuatan($muatan, 1);

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.serah-terima.simpan'), ['mode' => 'tengah_jalan', 'pengiriman_ids' => [$p->id], 'nama_penerima' => 'Warga'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['no_hp_penerima', 'alamat_penerima']);

    expect($p->fresh()->status_id)->toBe(stSlug('pengiriman'));
});

it('menolak resi milik muatan kurir lain', function () {
    $kurir = stPengguna(RoleEnum::COURIER->value);
    $kurirLain = stPengguna(RoleEnum::COURIER->value);
    [$p] = stResiDiMuatan(Muatan::factory()->untukKurir($kurirLain)->create(), 1);

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.serah-terima.periksa'), ['kode' => $p->no_resi, 'mode' => 'tujuan'])
        ->assertSuccessful()
        ->assertJsonPath('jumlah_boleh', 0)
        ->assertJsonPath('resi.0.boleh', false);

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.serah-terima.simpan'), ['mode' => 'tujuan', 'pengiriman_ids' => [$p->id], 'nama_penerima' => 'X'])
        ->assertUnprocessable();

    expect($p->fresh()->status_id)->toBe(stSlug('pengiriman'))
        ->and(SerahTerimaKurir::count())->toBe(0);
});

it('menolak resi yang tidak ada di muatan mana pun', function () {
    $kurir = stPengguna(RoleEnum::COURIER->value);
    $p = Pengiriman::factory()->create(['status_id' => stSlug('pengiriman')]);

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.serah-terima.simpan'), ['mode' => 'tujuan', 'pengiriman_ids' => [$p->id], 'nama_penerima' => 'X'])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($pesan) => str_contains($pesan, 'tidak ada di muatan'));
});

it('menolak serah terima ganda dan membatalkan seluruh kiriman bila satu resi gagal', function () {
    $kurir = stPengguna(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $resi = stResiDiMuatan($muatan, 2);
    tibakan($resi[0]);

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.serah-terima.simpan'), [
            'mode' => 'tujuan',
            'pengiriman_ids' => [$resi[0]->id, $resi[1]->id],
            'nama_penerima' => 'X',
            'foto' => [UploadedFile::fake()->image('a.jpg')],
        ])
        ->assertUnprocessable();

    // Semua atau tidak sama sekali — resi kedua tidak ikut berubah, fotonya tidak tertinggal.
    expect($resi[1]->fresh()->status_id)->toBe(stSlug('pengiriman'))
        ->and(SerahTerimaKurir::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('kerdus berisi dua tujuan: memeriksa memisahkan tujuan dan menyimpan campuran ditolak', function () {
    $kurir = stPengguna(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $a = stResiDiMuatan($muatan, 2);
    $b = stResiDiMuatan($muatan, 1, ['nama_lembaga' => 'Ponpes Darul Ulum', 'alamat_tujuan' => 'Jl. Kenanga 9']);
    stKerdus('KB-20261016-003-A5-01', [...$a, ...$b]);

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.serah-terima.periksa'), ['kode' => 'KB-20261016-003-A5-01', 'mode' => 'tujuan'])
        ->assertSuccessful()
        ->assertJsonCount(2, 'tujuan');

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.serah-terima.simpan'), ['mode' => 'tujuan', 'pengiriman_ids' => [$a[0]->id, $b[0]->id], 'nama_penerima' => 'X'])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($pesan) => str_contains($pesan, 'tujuan berbeda'));
});

it('menolak status Diterima langsung dari Proses Pengiriman di semua jalur', function () {
    $p = Pengiriman::factory()->create(['status_id' => stSlug('pengiriman')]);

    expect(fn () => $p->update(['status_id' => stSlug('diterima')]))->toThrow(DomainException::class);
    expect($p->fresh()->status_id)->toBe(stSlug('pengiriman'));
});

it('distribusi hanya menerima resi yang sudah Tiba, tanpa menimpa nama penerima dari kurir', function () {
    $kurir = stPengguna(RoleEnum::COURIER->value);
    $distribusi = stPengguna(RoleEnum::DISTRIBUSI->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $resi = stResiDiMuatan($muatan, 2);

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.serah-terima.simpan'), ['mode' => 'tujuan', 'pengiriman_ids' => [$resi[0]->id], 'nama_penerima' => 'Bu Siti'])
        ->assertSuccessful();

    $this->actingAs($distribusi)
        ->postJson(route('admin.muatan.selesaikan', $muatan))
        ->assertSuccessful()
        ->assertJsonPath('berhasil', 1)
        ->assertJsonPath('belum_tiba', 1);

    expect($resi[0]->fresh()->status_id)->toBe(stSlug('diterima'))
        ->and($resi[0]->fresh()->received_by)->toBe('Bu Siti')
        ->and($resi[1]->fresh()->status_id)->toBe(stSlug('pengiriman'));
});

it('halaman muatan menampilkan serah terima dan daftar tengah jalan kepada distribusi', function () {
    $kurir = stPengguna(RoleEnum::COURIER->value);
    $distribusi = stPengguna(RoleEnum::DISTRIBUSI->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $resi = stResiDiMuatan($muatan, 2);

    $this->actingAs($kurir)->postJson(route('admin.kurir.serah-terima.simpan'), [
        'mode' => 'tengah_jalan', 'pengiriman_ids' => [$resi[0]->id],
        'nama_penerima' => 'Warga Lerep', 'no_hp_penerima' => '0811', 'alamat_penerima' => 'Desa Lerep',
        'latitude' => -7.1, 'longitude' => 110.4,
    ])->assertSuccessful();

    $props = $this->actingAs($distribusi)
        ->get(route('admin.muatan.show', $muatan))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($props['muatan']['jumlah_tiba'])->toBe(1)
        ->and($props['tengahJalan'])->toHaveCount(1)
        ->and($props['tengahJalan'][0]['no_resi'])->toBe($resi[0]->no_resi)
        ->and($props['tengahJalan'][0]['tujuan_asal'])->toBe('TPQ Al-Ikhlas')
        ->and($props['tengahJalan'][0]['peta'])->toContain('openstreetmap.org');

    $baris = collect($props['items'])->firstWhere('pengiriman.id', $resi[0]->id);
    expect($baris['serah_terima']['nama_penerima'])->toBe('Warga Lerep');
});

it('kurir tanpa izin muatan.scan ditolak', function () {
    $tanpaIzin = User::factory()->create(['is_active' => true]);

    $this->actingAs($tanpaIzin)
        ->postJson(route('admin.kurir.serah-terima.periksa'), ['kode' => 'EQ-2026-00001', 'mode' => 'tujuan'])
        ->assertForbidden();
});
