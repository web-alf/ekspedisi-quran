<?php

use App\Models\Donatur;
use App\Models\Pengiriman;
use App\Models\User;
use App\Models\WakafItem;
use Database\Seeders\JenisQuranSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Donatur yang berdonasi rutin memakai kode donatur yang sama berulang kali.
 * Setiap penyimpanan harus MENAMBAH item baru saja — bukan menyalin ulang seluruh
 * item lama. Sebelum diperbaiki, satu donatur yang berdonasi tiga kali berakhir
 * dengan item dan resi berlipat ganda (nomor urut 1 muncul tiga kali), dan
 * pengiriman hantu ikut masuk ke alur gudang padahal mushafnya tidak pernah diminta.
 */
beforeEach(function () {
    // RefreshDatabase tidak menyemai peran/izin, jadi disiapkan sendiri.
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    foreach ([
        'dashboard.view', 'users.read', 'donatur.read', 'donatur.create',
        'donatur.update', 'donatur.delete',
    ] as $izin) {
        Permission::firstOrCreate(['name' => $izin, 'guard_name' => 'web']);
    }

    Role::findByName('super-admin')->syncPermissions(Permission::all());

    $this->seed(JenisQuranSeeder::class);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('super-admin');
});

/** Isian form donatur untuk satu kali donasi. */
function dataDonasi(array $ganti = []): array
{
    return array_merge([
        'kode_donatur' => 'RUTIN-01',
        'nama_donatur' => 'Hamba Allah Rutin',
        'no_hp' => '+6281234567890',
        'email_donatur' => 'rutin@uji.test',
        'alamat_donatur' => 'Jl. Uji No. 1',
        'jenis_wakaf_dipilih' => ['A5'],
        'jumlah_a5' => 2,
        'jumlah_a6' => 0,
        'jumlah_iqra' => 0,
        'donation_date' => '2026-01-10',
        'prayer_mode' => 'semua_donatur',
        'doa_untuk_semua' => 'Semoga berkah',
    ], $ganti);
}

it('tidak menggandakan item dan pengiriman saat donatur yang sama berdonasi lagi', function () {
    $this->actingAs($this->admin)->post('/admin/donatur', dataDonasi());

    $donatur = Donatur::where('kode_donatur', 'RUTIN-01')->firstOrFail();

    expect($donatur->wakafItems()->count())->toBe(2);
    expect(Pengiriman::where('donatur_id', $donatur->id)->count())->toBe(2);

    // Donasi kedua oleh orang yang sama: 1 mushaf lagi.
    $this->actingAs($this->admin)->post('/admin/donatur', dataDonasi([
        'jumlah_a5' => 1,
        'donation_date' => '2026-02-10',
    ]));

    $donatur->refresh();

    expect($donatur->total_a5_count)->toBe(3);
    expect($donatur->donation_count)->toBe(2);

    // Inti perbaikannya: total item 3, BUKAN 2 lalu ditambah 3 menjadi 5.
    expect($donatur->wakafItems()->count())->toBe(3);

    // Dan resinya sepadan dengan jumlah mushaf — tidak ada resi hantu.
    expect(Pengiriman::where('donatur_id', $donatur->id)->count())->toBe(3);
});

it('melanjutkan nomor urut item, tidak mengulang dari satu', function () {
    $this->actingAs($this->admin)->post('/admin/donatur', dataDonasi());
    $this->actingAs($this->admin)->post('/admin/donatur', dataDonasi([
        'jumlah_a5' => 2,
        'donation_date' => '2026-02-10',
    ]));

    $donatur = Donatur::where('kode_donatur', 'RUTIN-01')->firstOrFail();

    $urut = $donatur->wakafItems()
        ->where('wakaf_type', 'A5')
        ->orderBy('sequence_in_type')
        ->pluck('sequence_in_type')
        ->all();

    // Nomor urut berlanjut 1,2,3,4 — bukan 1,2 lalu 1,2 lagi.
    expect($urut)->toBe([1, 2, 3, 4]);

    // Tiap item juga punya nomor urut global sendiri.
    $global = $donatur->wakafItems()->pluck('global_sequence')->all();
    expect(array_unique($global))->toHaveCount(4);
});

it('menjaga jumlah item selalu sama dengan jumlah yang tercatat di kolom donatur', function () {
    foreach (['2026-01-10', '2026-02-10', '2026-03-10'] as $tanggal) {
        $this->actingAs($this->admin)->post('/admin/donatur', dataDonasi([
            'jumlah_a5' => 2,
            'donation_date' => $tanggal,
        ]));
    }

    $donatur = Donatur::where('kode_donatur', 'RUTIN-01')->firstOrFail();

    expect($donatur->donation_count)->toBe(3);
    expect($donatur->total_a5_count)->toBe(6);
    expect($donatur->wakafItems()->count())->toBe(6);
    expect(Pengiriman::where('donatur_id', $donatur->id)->count())->toBe(6);
});

it('memasang kunci unik sebagai jaring pengaman di database', function () {
    // Kunci ini yang memastikan bug serupa tidak bisa menyelinap lewat jalur mana pun,
    // sekarang maupun nanti. Migrasinya melewati pemasangan bila masih ada baris lama
    // yang bertabrakan, jadi tesnya sekaligus memastikan migrasi tidak diam-diam lewat.
    $index = collect(Schema::getIndexes('wakaf_items'))->pluck('name');

    expect($index)->toContain('wakaf_items_donatur_jenis_seq_unique');
});

it('menolak item dengan jenis dan nomor urut yang sama di tingkat database', function () {
    $this->actingAs($this->admin)->post('/admin/donatur', dataDonasi());

    $donatur = Donatur::where('kode_donatur', 'RUTIN-01')->firstOrFail();

    expect(fn () => WakafItem::create([
        'donatur_id' => $donatur->id,
        'wakaf_type' => 'A5',
        'sequence_in_type' => 1,
        'global_sequence' => 99,
        'wakif_name' => 'Paksa Ganda',
        'doa_request' => '',
        'relationship_to_donatur' => 'Diri sendiri',
        'status' => 'pending',
        'created_by' => $this->admin->id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('menggeser nomor urut donasi rutin bermode customize individual', function () {
    // Di mode ini nomor urut datang dari formulir dan selalu dihitung dari 1. Kalau
    // tidak digeser, donasi kedua akan memakai nomor yang sudah terpakai dan ditolak
    // kunci unik — donasi yang sebenarnya sah jadi gagal.
    // Nama wakif tidak boleh mengandung angka, jadi namanya ditulis dengan huruf.
    $namaWakif = [1 => 'Wakif Satu', 2 => 'Wakif Dua', 3 => 'Wakif Tiga'];

    $detail = fn (int $seq) => [
        'wakaf_type' => 'A5',
        'sequence_in_type' => $seq,
        'global_sequence' => $seq,
        'wakif_name' => $namaWakif[$seq],
        'doa_request' => 'Doa '.$namaWakif[$seq],
        'relationship_to_donatur' => 'Diri sendiri',
    ];

    $this->actingAs($this->admin)->post('/admin/donatur', dataDonasi([
        'prayer_mode' => 'customize_individual',
        'jumlah_a5' => 2,
        'wakif_details' => [$detail(1), $detail(2)],
    ]));

    // Donasi kedua, 1 mushaf lagi — formulir mengirim nomor urut 1 lagi.
    $this->actingAs($this->admin)->post('/admin/donatur', dataDonasi([
        'prayer_mode' => 'customize_individual',
        'jumlah_a5' => 1,
        'donation_date' => '2026-02-10',
        'wakif_details' => [$detail(1)],
    ]));

    $donatur = Donatur::where('kode_donatur', 'RUTIN-01')->firstOrFail();

    expect($donatur->wakafItems()->count())->toBe(3);

    $urut = $donatur->wakafItems()
        ->where('wakaf_type', 'A5')
        ->orderBy('sequence_in_type')
        ->pluck('sequence_in_type')
        ->all();

    // 1,2 dari donasi pertama; 3 dari donasi kedua — bukan 1,2,1.
    expect($urut)->toBe([1, 2, 3]);
});
