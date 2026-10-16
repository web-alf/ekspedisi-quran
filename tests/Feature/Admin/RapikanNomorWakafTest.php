<?php

use App\Models\Donatur;
use App\Models\Pengiriman;
use App\Models\User;
use App\Models\WakafItem;
use Database\Seeders\JenisQuranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Nomor urut item wakaf yang kembar pada donatur yang sama membuat nomor "#N" tampil
 * ganda dan menghalangi kunci unik (donatur_id, wakaf_type, sequence_in_type).
 *
 * Perbaikannya HANYA menyusun ulang nomor — tidak ada baris yang dihapus dan tidak ada
 * resi yang disentuh. Tes ini menjaga dua hal sekaligus: nomornya jadi rapi, DAN
 * datanya utuh.
 */
beforeEach(function () {
    $this->seed(JenisQuranSeeder::class);
    $this->admin = User::factory()->create(['is_active' => true]);
    $this->batal = DB::table('status_pengiriman')->where('slug', 'batal')->value('id');
    $this->jenisA5 = DB::table('jenis_quran')->where('kode_jenis', 'A5')->value('id');

    // Kunci unik ini ADA di basis data uji (dipasang saat migrasi pada basis data kosong),
    // tetapi TIDAK ADA di produksi — di sana migrasinya dilewati karena 191 nomor kembar
    // sudah terlanjur ada. Keadaan yang harus diuji adalah keadaan produksi, jadi kuncinya
    // diturunkan dulu supaya baris kembar bisa dibuat sama seperti di sana.
    $ada = collect(DB::select('SHOW INDEX FROM wakaf_items'))
        ->contains(fn ($i) => $i->Key_name === 'wakaf_items_donatur_jenis_seq_unique');

    if ($ada) {
        DB::statement('ALTER TABLE wakaf_items DROP INDEX wakaf_items_donatur_jenis_seq_unique');
    }
});

afterEach(function () {
    // Dipasang kembali supaya basis data uji tidak ditinggalkan dalam keadaan berbeda —
    // kalau tidak, tes lain yang berjalan setelah ini ikut kehilangan kuncinya.
    if (kunciUnikAda()) {
        return;
    }

    $masihKembar = DB::table('wakaf_items')
        ->select('donatur_id', 'wakaf_type', 'sequence_in_type')
        ->groupBy('donatur_id', 'wakaf_type', 'sequence_in_type')
        ->havingRaw('COUNT(*) > 1')
        ->count();

    // Hanya bisa dipasang bila tidak ada kembar; kalau ada (tes sengaja membuatnya dan
    // tidak menjalankan perbaikannya), biarkan — tes berikutnya menurunkannya lagi.
    if ($masihKembar === 0) {
        DB::statement('ALTER TABLE wakaf_items ADD UNIQUE wakaf_items_donatur_jenis_seq_unique (donatur_id, wakaf_type, sequence_in_type)');
    }
});

function kunciUnikAda(): bool
{
    return collect(DB::select('SHOW INDEX FROM wakaf_items'))
        ->contains(fn ($i) => $i->Key_name === 'wakaf_items_donatur_jenis_seq_unique');
}

/**
 * Membuat donatur dengan item wakaf bernomor sesuai yang diminta.
 * Nomor sengaja boleh kembar — itu justru keadaan yang diperbaiki.
 *
 * @param  array<int, array{wakaf_type: string, sequence_in_type: int, global_sequence: int}>  $item
 */
function donaturDenganItem(string $kode, array $item, User $admin, ?int $jenisId = null, ?int $batal = null): Donatur
{
    $d = Donatur::create([
        'kode_donatur' => $kode,
        'nama_donatur' => 'Uji '.$kode,
        'no_hp' => '+6281234567890',
        'jenis_wakaf_dipilih' => ['A5'],
        'total_a5_count' => count($item), 'total_a6_count' => 0, 'total_iqra_count' => 0,
        'donation_count' => 1,
        'donation_date' => '2026-01-10',
        'prayer_mode' => 'semua_donatur',
        'created_by' => $admin->id,
    ]);

    foreach ($item as $i => $spec) {
        $kirim = Pengiriman::create([
            'donatur_id' => $d->id,
            'jenis_quran_id' => $jenisId,
            'status_id' => $batal,
            'no_resi' => 'EQ-UJI-'.$kode.'-'.($i + 1),
            'tanggal_wakaf' => '2026-01-10',
            'jumlah' => 1,
            'created_by' => $admin->id,
        ]);

        WakafItem::create([
            'donatur_id' => $d->id,
            'pengiriman_id' => $kirim->id,
            'wakaf_type' => $spec['wakaf_type'],
            'sequence_in_type' => $spec['sequence_in_type'],
            'global_sequence' => $spec['global_sequence'],
            'wakif_name' => 'Wakif '.($i + 1),
            'doa_request' => 'Doa '.($i + 1),
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);
    }

    return $d;
}

it('menyusun ulang nomor kembar tanpa menghapus satu baris pun', function () {
    $d = donaturDenganItem('REN-01', [
        ['wakaf_type' => 'A5', 'sequence_in_type' => 1, 'global_sequence' => 1],
        ['wakaf_type' => 'A5', 'sequence_in_type' => 1, 'global_sequence' => 2],
        ['wakaf_type' => 'A5', 'sequence_in_type' => 2, 'global_sequence' => 3],
    ], $this->admin, $this->jenisA5, $this->batal);

    $idItemSebelum = WakafItem::where('donatur_id', $d->id)->orderBy('id')->pluck('id')->all();
    $jumlahSebelum = WakafItem::count();
    $jumlahResiSebelum = Pengiriman::count();

    $this->artisan('wakaf-items:renumber-sequences')
        ->assertSuccessful();

    // Nomornya kini 1, 2, 3 — rapi.
    expect(WakafItem::where('donatur_id', $d->id)->orderBy('id')->pluck('sequence_in_type')->all())
        ->toBe([1, 2, 3]);

    // GLOBAL-nya juga rapi, karena dipakai sebagai "#N" di tampilan.
    expect(WakafItem::where('donatur_id', $d->id)->orderBy('id')->pluck('global_sequence')->all())
        ->toBe([1, 2, 3]);

    // TIDAK ADA yang hilang.
    expect(WakafItem::count())->toBe($jumlahSebelum);
    expect(Pengiriman::count())->toBe($jumlahResiSebelum);
    expect(WakafItem::where('donatur_id', $d->id)->orderBy('id')->pluck('id')->all())->toBe($idItemSebelum);
});

it('tidak menyentuh resi maupun isi item', function () {
    // Perbaikan ini hanya soal NOMOR. Nama wakif, doa, dan resinya harus tidak berubah.
    $d = donaturDenganItem('REN-02', [
        ['wakaf_type' => 'A5', 'sequence_in_type' => 1, 'global_sequence' => 1],
        ['wakaf_type' => 'A5', 'sequence_in_type' => 1, 'global_sequence' => 1],
    ], $this->admin, $this->jenisA5, $this->batal);

    $resisSebelum = Pengiriman::where('donatur_id', $d->id)->orderBy('id')->pluck('no_resi')->all();
    $wakifSebelum = WakafItem::where('donatur_id', $d->id)->orderBy('id')->pluck('wakif_name')->all();
    $doaSebelum = WakafItem::where('donatur_id', $d->id)->orderBy('id')->pluck('doa_request')->all();
    $berkasSebelum = WakafItem::where('donatur_id', $d->id)->orderBy('id')->pluck('pengiriman_id')->all();

    $this->artisan('wakaf-items:renumber-sequences')->assertSuccessful();

    expect(Pengiriman::where('donatur_id', $d->id)->orderBy('id')->pluck('no_resi')->all())->toBe($resisSebelum);
    expect(WakafItem::where('donatur_id', $d->id)->orderBy('id')->pluck('wakif_name')->all())->toBe($wakifSebelum);
    expect(WakafItem::where('donatur_id', $d->id)->orderBy('id')->pluck('doa_request')->all())->toBe($doaSebelum);
    expect(WakafItem::where('donatur_id', $d->id)->orderBy('id')->pluck('pengiriman_id')->all())->toBe($berkasSebelum);
});

it('mode uji-coba tidak mengubah apa pun', function () {
    $d = donaturDenganItem('REN-03', [
        ['wakaf_type' => 'A5', 'sequence_in_type' => 1, 'global_sequence' => 1],
        ['wakaf_type' => 'A5', 'sequence_in_type' => 1, 'global_sequence' => 2],
    ], $this->admin, $this->jenisA5, $this->batal);

    $sebelum = WakafItem::orderBy('id')->get(['id', 'sequence_in_type', 'global_sequence'])->toArray();

    $this->artisan('wakaf-items:renumber-sequences --dry-run')->assertSuccessful();

    expect(WakafItem::orderBy('id')->get(['id', 'sequence_in_type', 'global_sequence'])->toArray())
        ->toEqual($sebelum);
});

it('menomori ulang lintas jenis secara terpisah dan berurutan', function () {
    // Nomor dihitung per jenis: A5 punya urutannya sendiri, A6 punya sendiri.
    $d = donaturDenganItem('REN-04', [
        ['wakaf_type' => 'A5', 'sequence_in_type' => 2, 'global_sequence' => 9],
        ['wakaf_type' => 'A5', 'sequence_in_type' => 2, 'global_sequence' => 9],
        ['wakaf_type' => 'A6', 'sequence_in_type' => 1, 'global_sequence' => 5],
        ['wakaf_type' => 'A6', 'sequence_in_type' => 1, 'global_sequence' => 5],
    ], $this->admin, $this->jenisA5, $this->batal);

    $this->artisan('wakaf-items:renumber-sequences')->assertSuccessful();

    $baris = WakafItem::where('donatur_id', $d->id)->orderBy('id')->get(['wakaf_type', 'sequence_in_type', 'global_sequence']);

    expect($baris->pluck('sequence_in_type')->all())->toBe([1, 2, 1, 2]);
    expect($baris->pluck('global_sequence')->all())->toBe([1, 2, 3, 4]);
});

it('membiarkan donatur yang nomornya sudah rapi sama sekali tidak tersentuh', function () {
    $d = donaturDenganItem('REN-05', [
        ['wakaf_type' => 'A5', 'sequence_in_type' => 1, 'global_sequence' => 1],
        ['wakaf_type' => 'A5', 'sequence_in_type' => 2, 'global_sequence' => 2],
    ], $this->admin, $this->jenisA5, $this->batal);

    $sebelum = WakafItem::where('donatur_id', $d->id)->orderBy('id')
        ->get(['id', 'sequence_in_type', 'global_sequence'])->toArray();

    $this->artisan('wakaf-items:renumber-sequences')->assertSuccessful();

    expect(WakafItem::where('donatur_id', $d->id)->orderBy('id')
        ->get(['id', 'sequence_in_type', 'global_sequence'])->toArray())->toEqual($sebelum);
});

it('bisa dibatasi ke satu donatur saja', function () {
    $a = donaturDenganItem('REN-06A', [
        ['wakaf_type' => 'A5', 'sequence_in_type' => 1, 'global_sequence' => 1],
        ['wakaf_type' => 'A5', 'sequence_in_type' => 1, 'global_sequence' => 2],
    ], $this->admin, $this->jenisA5, $this->batal);

    $b = donaturDenganItem('REN-06B', [
        ['wakaf_type' => 'A5', 'sequence_in_type' => 1, 'global_sequence' => 1],
        ['wakaf_type' => 'A5', 'sequence_in_type' => 1, 'global_sequence' => 2],
    ], $this->admin, $this->jenisA5, $this->batal);

    $this->artisan('wakaf-items:renumber-sequences --donatur='.$a->id)->assertSuccessful();

    // Yang diminta dibatasi jadi rapi...
    expect(WakafItem::where('donatur_id', $a->id)->pluck('sequence_in_type')->all())->toBe([1, 2]);

    // ...yang lain dibiarkan apa adanya.
    expect(WakafItem::where('donatur_id', $b->id)->pluck('sequence_in_type')->all())->toBe([1, 1]);
});

it('membereskan nomor "#N" kembar yang muncul tanpa nomor jenis kembar', function () {
    // Kasus nyata di produksi (donatur MDI419): A5#1 dan A6#1 sama-sama memakai global 1,
    // sehingga donatur itu menampilkan DUA item bernomor "#1" — padahal sequence_in_type
    // -nya sah, tidak kembar, dan tidak menghalangi kunci unik apa pun. Kalau perintahnya
    // hanya memeriksa nomor jenis kembar, donatur ini tidak tersentuh dan "#N" tetap ganda.
    $d = donaturDenganItem('REN-08', [
        ['wakaf_type' => 'A5', 'sequence_in_type' => 1, 'global_sequence' => 1],
        ['wakaf_type' => 'A6', 'sequence_in_type' => 1, 'global_sequence' => 1],
        ['wakaf_type' => 'A6', 'sequence_in_type' => 2, 'global_sequence' => 2],
    ], $this->admin, $this->jenisA5, $this->batal);

    // Tidak ada nomor jenis kembar — inilah yang membuat kasus ini mudah terlewat.
    expect(DB::table('wakaf_items')
        ->select('donatur_id', 'wakaf_type', 'sequence_in_type')
        ->groupBy('donatur_id', 'wakaf_type', 'sequence_in_type')
        ->havingRaw('COUNT(*) > 1')->count())->toBe(0);

    $this->artisan('wakaf-items:renumber-sequences')->assertSuccessful();

    // "#N" kini 1, 2, 3 — tidak ada lagi dua item bernomor sama.
    expect(WakafItem::where('donatur_id', $d->id)->orderBy('id')->pluck('global_sequence')->all())
        ->toBe([1, 2, 3]);

    // Nomor per jenisnya tetap benar.
    expect(WakafItem::where('donatur_id', $d->id)->orderBy('id')->pluck('sequence_in_type')->all())
        ->toBe([1, 1, 2]);
});

it('membuat kunci unik bisa dipasang di atas data yang sudah dirapikan', function () {
    // Inilah tujuannya: setelah rapi, kunci unik (yang selama ini terhalang) bisa dipasang.
    donaturDenganItem('REN-07', [
        ['wakaf_type' => 'A5', 'sequence_in_type' => 1, 'global_sequence' => 1],
        ['wakaf_type' => 'A5', 'sequence_in_type' => 1, 'global_sequence' => 2],
        ['wakaf_type' => 'A5', 'sequence_in_type' => 3, 'global_sequence' => 3],
    ], $this->admin, $this->jenisA5, $this->batal);

    // Sebelum dirapikan, kunci unik memang tidak bisa dipasang.
    expect(DB::table('wakaf_items')
        ->select('donatur_id', 'wakaf_type', 'sequence_in_type')
        ->groupBy('donatur_id', 'wakaf_type', 'sequence_in_type')
        ->havingRaw('COUNT(*) > 1')->count())->toBeGreaterThan(0);

    $this->artisan('wakaf-items:renumber-sequences')->assertSuccessful();

    expect(DB::table('wakaf_items')
        ->select('donatur_id', 'wakaf_type', 'sequence_in_type')
        ->groupBy('donatur_id', 'wakaf_type', 'sequence_in_type')
        ->havingRaw('COUNT(*) > 1')->count())->toBe(0);

    // Dan benar-benar bisa dipasang.
    DB::statement('ALTER TABLE wakaf_items ADD UNIQUE wakaf_items_donatur_jenis_seq_unique (donatur_id, wakaf_type, sequence_in_type)');

    $terpasang = collect(DB::select('SHOW INDEX FROM wakaf_items'))
        ->contains(fn ($i) => $i->Key_name === 'wakaf_items_donatur_jenis_seq_unique');

    expect($terpasang)->toBeTrue();
});
