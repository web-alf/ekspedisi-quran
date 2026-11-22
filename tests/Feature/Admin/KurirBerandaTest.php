<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Muatan;
use App\Models\MuatanItem;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\SerahTerimaKurir;
use App\Models\StatusPengiriman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * Beranda kurir "Hari Ini & Rute" (/admin/kurir): muatan yang sedang dibawa dan
 * tujuan-tujuannya, dikelompokkan dari resi di dalam muatan.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([PermissionEnum::MUATAN_READ, PermissionEnum::MUATAN_SCAN] as $izin) {
        Permission::firstOrCreate(['name' => $izin->value, 'guard_name' => 'web']);
    }

    Role::firstOrCreate(['name' => RoleEnum::COURIER->value], ['display_name' => 'Kurir', 'guard_name' => 'web'])
        ->syncPermissions([PermissionEnum::MUATAN_READ->value, PermissionEnum::MUATAN_SCAN->value]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['pengiriman' => 6, 'tiba' => 7, 'diterima' => 8, 'batal' => 9] as $slug => $urutan) {
        StatusPengiriman::updateOrCreate(['slug' => $slug], ['nama' => ucfirst($slug), 'urutan' => $urutan, 'is_active' => true]);
    }
});

function berandaKurir(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::COURIER->value);

    return $user;
}

/**
 * Resi di muatan untuk satu tujuan.
 *
 * @return array<int, Pengiriman>
 */
function berandaResi(Muatan $muatan, int $jumlah, string $slug, array $atribut = []): array
{
    $resi = [];

    for ($i = 0; $i < $jumlah; $i++) {
        $p = Pengiriman::factory()->create($atribut + [
            'status_id' => StatusPengiriman::where('slug', $slug)->value('id'),
            'nama_lembaga' => 'TPQ Al-Ikhlas',
            'nama_penerima' => 'Ustadz Ahmad',
            'alamat_tujuan' => 'Jl. Melati 1, Semarang',
            'no_hp_penerima' => '081234567890',
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
function berandaKerdus(string $kode, array $resi): void
{
    $box = PackingBox::factory()->create([
        'kode_kerdus' => $kode,
        'daily_packing_task_id' => DailyPackingTask::factory()->create()->id,
    ]);

    foreach (array_values($resi) as $i => $p) {
        PackingItem::factory()->create(['packing_box_id' => $box->id, 'pengiriman_id' => $p->id, 'urutan_dalam_box' => $i + 1]);
    }
}

it('menampilkan muatan aktif dengan tujuan yang dikelompokkan dan berstatus', function () {
    $kurir = berandaKurir();
    $muatan = Muatan::factory()->untukKurir($kurir)->create(['kode_muatan' => 'MUK-2026-00148']);

    $tiba = berandaResi($muatan, 2, 'tiba', ['nama_lembaga' => 'MI Nurul Huda', 'alamat_tujuan' => 'Kp. Sawah, Dramaga']);
    $berikut = berandaResi($muatan, 3, 'pengiriman', ['nama_lembaga' => 'Pesantren Darul Falah', 'alamat_tujuan' => 'Jl. Raya Ciomas 88']);
    berandaResi($muatan, 1, 'pengiriman', ['nama_lembaga' => 'Lapas Bogor', 'alamat_tujuan' => 'Jl. Paledang 2', 'jumlah_quran' => 40]);

    berandaKerdus('KB-20261121-007-A5-01', [$berikut[0], $berikut[1]]);
    berandaKerdus('KB-20261121-007-A5-02', [$berikut[2]]);

    foreach ($tiba as $p) {
        SerahTerimaKurir::create([
            'pengiriman_id' => $p->id,
            'muatan_id' => $muatan->id,
            'user_id' => $kurir->id,
            'jenis' => SerahTerimaKurir::JENIS_TUJUAN,
            'nama_penerima' => 'Pak Jajang',
        ])->forceFill(['created_at' => Carbon::parse('2026-11-21 08:42:00')])->save();
    }

    $this->actingAs($kurir)
        ->get(route('admin.kurir.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Kurir/Beranda')
            ->where('muatan.kode_muatan', 'MUK-2026-00148')
            ->where('muatan.total_resi', 6)
            ->where('muatan.total_mushaf', 45)
            ->where('muatan.total_kerdus', 2)
            ->where('muatan.jumlah_tujuan', 3)
            ->where('muatan.jumlah_tujuan_tiba', 1)
            ->has('tujuan', 3)
            ->where('tujuan.0.nama', 'MI Nurul Huda')
            ->where('tujuan.0.status', 'tiba')
            ->where('tujuan.0.tiba_pukul', '08.42')
            ->where('tujuan.1.nama', 'Pesantren Darul Falah')
            ->where('tujuan.1.status', 'berikutnya')
            ->where('tujuan.1.penerima', 'Ustadz Ahmad')
            ->where('tujuan.1.no_hp', '081234567890')
            ->where('tujuan.1.jumlah_resi', 3)
            ->where('tujuan.1.jumlah_kerdus', 2)
            ->where('tujuan.1.tiba_pukul', null)
            ->where('tujuan.2.status', 'menunggu')
            ->where('tujuan.2.jumlah_mushaf', 40)
        );
});

it('mengelompokkan tujuan tanpa peduli huruf besar dan spasi, dan memakai nama penerima bila tanpa lembaga', function () {
    $kurir = berandaKurir();
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    berandaResi($muatan, 1, 'pengiriman', ['nama_lembaga' => 'TPQ Al-Ikhlas', 'alamat_tujuan' => 'Jl. Melati 1']);
    berandaResi($muatan, 1, 'pengiriman', ['nama_lembaga' => ' tpq al-ikhlas ', 'alamat_tujuan' => 'JL. MELATI 1']);
    berandaResi($muatan, 1, 'pengiriman', ['nama_lembaga' => null, 'nama_penerima' => 'Bu Siti', 'alamat_tujuan' => 'Jl. Kenanga 3']);

    $this->actingAs($kurir)
        ->get(route('admin.kurir.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tujuan', 2)
            ->where('tujuan.0.jumlah_resi', 2)
            ->where('tujuan.1.nama', 'Bu Siti')
            ->where('tujuan.1.penerima', null)
        );
});

it('tidak menghitung resi yang batal', function () {
    $kurir = berandaKurir();
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    berandaResi($muatan, 2, 'pengiriman');
    berandaResi($muatan, 1, 'batal');

    $this->actingAs($kurir)
        ->get(route('admin.kurir.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('muatan.total_resi', 2)
            ->has('tujuan', 1)
        );
});

it('memilih muatan yang belum selesai, bukan muatan terbaru yang sudah sampai semua', function () {
    $kurir = berandaKurir();

    $berjalan = Muatan::factory()->untukKurir($kurir)->tanggal('2026-11-20')->create(['kode_muatan' => 'MUK-2026-00141']);
    berandaResi($berjalan, 1, 'pengiriman');

    $selesai = Muatan::factory()->untukKurir($kurir)->tanggal('2026-11-21')->create(['kode_muatan' => 'MUK-2026-00148']);
    berandaResi($selesai, 2, 'diterima');

    $this->actingAs($kurir)
        ->get(route('admin.kurir.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('muatan.kode_muatan', 'MUK-2026-00141'));
});

it('menampilkan muatan baru yang belum diisi supaya kurir bisa mulai memuat', function () {
    $kurir = berandaKurir();
    Muatan::factory()->untukKurir($kurir)->create(['kode_muatan' => 'MUK-2026-00150']);

    $this->actingAs($kurir)
        ->get(route('admin.kurir.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('muatan.kode_muatan', 'MUK-2026-00150')
            ->where('muatan.jumlah_tujuan', 0)
            ->has('tujuan', 0)
        );
});

it('kosong bila semua muatan kurir sudah sampai', function () {
    $kurir = berandaKurir();
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    berandaResi($muatan, 2, 'tiba');

    $this->actingAs($kurir)
        ->get(route('admin.kurir.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('muatan', null)->has('tujuan', 0)->has('diJalan', 0));
});

it('tidak menampilkan muatan milik kurir lain', function () {
    $kurir = berandaKurir();
    $muatanLain = Muatan::factory()->untukKurir(berandaKurir())->create();
    berandaResi($muatanLain, 1, 'pengiriman');

    $this->actingAs($kurir)
        ->get(route('admin.kurir.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('muatan', null));
});

it('menolak pengguna tanpa izin muatan.read', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user)->get(route('admin.kurir.dashboard'))->assertForbidden();
});

/**
 * Tiru yang ditulis SerahTerimaKurirService saat mushaf diminta di tengah jalan: resi
 * dialihkan ke penerima baru, langsung Tiba, dan tujuan asalnya dicatat di serah terima.
 */
function berandaSerahkanDiJalan(Muatan $muatan, User $kurir, Pengiriman $p, string $nama, string $alamat, string $pukul = '10.41'): void
{
    SerahTerimaKurir::create([
        'pengiriman_id' => $p->id,
        'muatan_id' => $muatan->id,
        'user_id' => $kurir->id,
        'jenis' => SerahTerimaKurir::JENIS_TENGAH_JALAN,
        'nama_penerima' => $nama,
        'no_hp_penerima' => '085722110934',
        'alamat_penerima' => $alamat,
        'nama_lembaga_asal' => $p->nama_lembaga,
        'nama_penerima_asal' => $p->nama_penerima,
        'alamat_asal' => $p->alamat_tujuan,
    ])->forceFill(['created_at' => Carbon::parse("2026-11-22 {$pukul}:00")])->save();

    $p->update([
        'nama_penerima' => $nama,
        'alamat_tujuan' => $alamat,
        'nama_lembaga' => null,
        'status_id' => StatusPengiriman::where('slug', 'tiba')->value('id'),
    ]);
}

it('mushaf yang diminta di jalan tidak menjadi tujuan baru dan tidak menaikkan progres', function () {
    $kurir = berandaKurir();
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    $darulFalah = berandaResi($muatan, 3, 'pengiriman', ['nama_lembaga' => 'Pesantren Darul Falah', 'alamat_tujuan' => 'Jl. Raya Ciomas 88']);
    berandaResi($muatan, 1, 'pengiriman', ['nama_lembaga' => 'Lapas Bogor', 'alamat_tujuan' => 'Jl. Paledang 2']);
    berandaKerdus('KB-20261122-007-A5-01', $darulFalah);

    berandaSerahkanDiJalan($muatan, $kurir, $darulFalah[0], 'Pak Ahmad', 'Jl. Raya Dramaga Km 7');

    $this->actingAs($kurir)
        ->get(route('admin.kurir.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tujuan', 2)
            ->where('muatan.jumlah_tujuan', 2)
            ->where('muatan.jumlah_tujuan_tiba', 0)
            ->where('muatan.total_kerdus', 1)
            ->where('muatan.total_resi', 4)
            ->where('muatan.total_mushaf', 4)
            ->where('tujuan.0.nama', 'Pesantren Darul Falah')
            ->where('tujuan.0.status', 'berikutnya')
            ->where('tujuan.0.jumlah_resi', 2)
            ->where('tujuan.0.jumlah_mushaf', 2)
            ->where('tujuan.0.jumlah_kerdus', 1)
            ->where('tujuan.0.diserahkan_di_jalan', 1)
            ->where('tujuan.1.nama', 'Lapas Bogor')
            ->where('tujuan.1.diserahkan_di_jalan', 0)
            ->has('diJalan', 1)
            ->where('diJalan.0.nama', 'Pak Ahmad')
            ->where('diJalan.0.alamat', 'Jl. Raya Dramaga Km 7')
            ->where('diJalan.0.jumlah_mushaf', 1)
            ->where('diJalan.0.dari', 'Pesantren Darul Falah')
            ->where('diJalan.0.pukul', '10.41')
        );
});

it('tujuan yang seluruh mushafnya diminta di jalan tetap tampil dan keluar dari hitungan progres', function () {
    $kurir = berandaKurir();
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    berandaResi($muatan, 1, 'pengiriman', ['nama_lembaga' => 'Pesantren Darul Falah', 'alamat_tujuan' => 'Jl. Raya Ciomas 88']);
    $lapas = berandaResi($muatan, 2, 'pengiriman', ['nama_lembaga' => 'Lapas Bogor', 'alamat_tujuan' => 'Jl. Paledang 2', 'jumlah_quran' => 20]);

    berandaSerahkanDiJalan($muatan, $kurir, $lapas[0], 'Pak Ahmad', 'Jl. Raya Dramaga Km 7', '10.41');
    berandaSerahkanDiJalan($muatan, $kurir, $lapas[1], 'pak ahmad ', 'Jl. Raya Dramaga Km 7', '10.43');

    $this->actingAs($kurir)
        ->get(route('admin.kurir.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tujuan', 2)
            ->where('muatan.jumlah_tujuan', 1)
            ->where('tujuan.1.nama', 'Lapas Bogor')
            ->where('tujuan.1.alamat', 'Jl. Paledang 2')
            ->where('tujuan.1.status', 'di_jalan')
            ->where('tujuan.1.jumlah_resi', 0)
            ->where('tujuan.1.jumlah_mushaf', 0)
            ->where('tujuan.1.diserahkan_di_jalan', 40)
            ->has('diJalan', 1)
            ->where('diJalan.0.jumlah_mushaf', 40)
            ->where('diJalan.0.pukul', '10.43')
        );
});

it('tujuan tetap tiba bila resi sisanya sudah diserahkan meski sebagian diminta di jalan', function () {
    $kurir = berandaKurir();
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    $resi = berandaResi($muatan, 2, 'pengiriman', ['nama_lembaga' => 'Pesantren Darul Falah', 'alamat_tujuan' => 'Jl. Raya Ciomas 88']);
    berandaResi($muatan, 1, 'pengiriman', ['nama_lembaga' => 'Lapas Bogor', 'alamat_tujuan' => 'Jl. Paledang 2']);

    berandaSerahkanDiJalan($muatan, $kurir, $resi[0], 'Pak Ahmad', 'Jl. Raya Dramaga Km 7');
    $resi[1]->update(['status_id' => StatusPengiriman::where('slug', 'tiba')->value('id')]);

    $this->actingAs($kurir)
        ->get(route('admin.kurir.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tujuan.0.status', 'tiba')
            ->where('tujuan.1.status', 'berikutnya')
            ->where('muatan.jumlah_tujuan', 2)
            ->where('muatan.jumlah_tujuan_tiba', 1)
        );
});

it('kerdus berisi resi untuk dua tujuan dihitung sekali di total muatan', function () {
    $kurir = berandaKurir();
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    $a = berandaResi($muatan, 2, 'pengiriman', ['nama_lembaga' => 'Pesantren Darul Falah', 'alamat_tujuan' => 'Jl. Raya Ciomas 88']);
    $b = berandaResi($muatan, 2, 'pengiriman', ['nama_lembaga' => 'Lapas Bogor', 'alamat_tujuan' => 'Jl. Paledang 2']);
    berandaKerdus('KB-20261122-007-A5-01', [...$a, ...$b]);

    $this->actingAs($kurir)
        ->get(route('admin.kurir.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('muatan.total_kerdus', 1)
            ->where('tujuan.0.jumlah_kerdus', 1)
            ->where('tujuan.1.jumlah_kerdus', 1)
        );
});
