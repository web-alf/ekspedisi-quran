<?php

use App\Models\Donatur;
use App\Models\Pengiriman;
use App\Models\SerahTerimaKurir;
use App\Models\StatusHistory;
use App\Models\StatusPengiriman;
use App\Models\TrackingHistory;
use App\Models\User;
use App\Models\WakafItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/*
 * Riwayat di halaman lacak PUBLIK (16/10/2026): hanya tahap penting, satu entri per tahap,
 * tanpa catatan petugas dan nama petugas. Riwayat mentah adalah log kerja internal —
 * pindai massal, koreksi bolak-balik, catatan uji, isi doa dari impor — dan dulu seluruhnya
 * ikut terkirim ke peramban publik.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'StatusPengirimanSeeder']);
    Cache::forget('status_pengiriman_active');
});

function rpStatus(string $slug): int
{
    return (int) StatusPengiriman::where('slug', $slug)->value('id');
}

function rpResi(string $slug, array $atribut = []): Pengiriman
{
    return Pengiriman::factory()->create($atribut + [
        'no_resi' => 'EQ-2026-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'status_id' => rpStatus($slug),
    ]);
}

function rpRiwayat(Pengiriman $p, $test): array
{
    return $test->get('/tracking/'.$p->no_resi)->assertOk()->viewData('page')['props']['riwayat'];
}

function rpPindah(Pengiriman $p, string $dari, string $ke, string $waktu, array $lain = []): void
{
    StatusHistory::create($lain + [
        'pengiriman_id' => $p->id,
        'status_from' => rpStatus($dari),
        'status_to' => rpStatus($ke),
        'catatan' => 'Bulk update status',
        'created_by' => User::factory()->create(['name' => 'Staff Gudang'])->id,
    ])->forceFill(['created_at' => $waktu])->saveQuietly();
}

it('hanya menampilkan satu entri per tahap dan menyembunyikan langkah yang ditarik koreksi', function () {
    $p = rpResi('packing', ['created_at' => '2026-10-01 07:00:00']);

    // Pola nyata EQ-2026-21534: maju sampai selesai packing, dikoreksi mundur ke pemesanan,
    // lalu maju lagi ke packing.
    rpPindah($p, 'pemesanan', 'produksi', '2026-10-13 08:23:13');
    rpPindah($p, 'produksi', 'kedatangan', '2026-10-13 08:24:27');
    rpPindah($p, 'kedatangan', 'packing', '2026-10-13 08:56:13');
    rpPindah($p, 'packing', 'selesai-packing', '2026-10-13 08:56:31');
    rpPindah($p, 'selesai-packing', 'pemesanan', '2026-10-15 09:12:33');
    rpPindah($p, 'pemesanan', 'packing', '2026-10-16 08:30:49');

    $riwayat = rpRiwayat($p, $this);

    // Terbaru di atas; kedatangan (tahap internal) dan selesai-packing (sudah ditarik) hilang.
    // Pemesanan memakai waktu resi dibuat, bukan waktu koreksi 15/10 — supaya tidak tampil
    // lebih baru daripada Produksi.
    expect(array_column($riwayat, 'slug'))->toBe(['packing', 'produksi', 'pemesanan'])
        ->and(array_column($riwayat, 'waktu'))->toBe(['16/10/2026 08:30', '13/10/2026 08:23', '01/10/2026 07:00']);
});

it('status sekarang yang dicapai ulang tanpa riwayat memakai waktu terakhir resi diperbarui', function () {
    // Pola nyata EQ-2026-21534: kembali ke packing 16/10 08:30, lalu disegel di kerdus 08:38
    // (penyegelan tidak menulis riwayat status) — riwayat Selesai Packing terakhir 13/10.
    $p = rpResi('selesai-packing', ['created_at' => '2026-10-01 07:00:00']);
    rpPindah($p, 'pemesanan', 'produksi', '2026-10-13 08:23:13');
    rpPindah($p, 'produksi', 'packing', '2026-10-13 08:56:13');
    rpPindah($p, 'packing', 'selesai-packing', '2026-10-13 08:56:31');
    rpPindah($p, 'selesai-packing', 'pemesanan', '2026-10-15 09:12:33');
    rpPindah($p, 'pemesanan', 'packing', '2026-10-16 08:30:49');
    DB::table('pengiriman')->where('id', $p->id)->update(['updated_at' => '2026-10-16 08:38:20']);

    $riwayat = rpRiwayat($p->fresh(), $this);

    expect(array_column($riwayat, 'slug'))->toBe(['selesai-packing', 'packing', 'produksi', 'pemesanan'])
        ->and(array_column($riwayat, 'waktu'))->toBe(['16/10/2026 08:38', '16/10/2026 08:30', '13/10/2026 08:23', '01/10/2026 07:00']);
});

it('waktu tiap tahap tidak pernah lebih baru daripada tahap di atasnya', function () {
    $p = rpResi('pengiriman', ['created_at' => '2026-10-01 07:00:00']);
    rpPindah($p, 'pemesanan', 'packing', '2026-10-10 08:00:00');
    rpPindah($p, 'packing', 'pemesanan', '2026-10-12 08:00:00');
    rpPindah($p, 'pemesanan', 'packing', '2026-10-14 08:00:00');
    rpPindah($p, 'packing', 'selesai-packing', '2026-10-14 09:00:00');
    rpPindah($p, 'selesai-packing', 'pengiriman', '2026-10-15 09:00:00');

    $waktu = collect(rpRiwayat($p, $this))
        ->map(fn (array $r) => Carbon\Carbon::createFromFormat('d/m/Y H:i', $r['waktu']));

    expect($waktu->values()->all())->toEqual($waktu->sortDesc()->values()->all());
});

it('tidak mengirim catatan petugas, nama petugas, isi doa, maupun no HP donatur ke halaman publik', function () {
    $donatur = Donatur::factory()->create(['no_hp' => '081299998888']);
    $p = rpResi('produksi', ['donatur_id' => $donatur->id]);
    rpPindah($p, 'pemesanan', 'produksi', '2026-10-13 08:00:00', [
        'catatan' => 'Donatur: Tito | Doa: Semoga menjadi imam yang soleh',
        'created_by' => User::factory()->create(['name' => 'Administrator System'])->id,
    ]);

    $html = $this->get('/tracking/'.$p->no_resi)->assertOk()->getContent();

    expect($html)->not->toContain('Semoga menjadi imam')
        ->not->toContain('Administrator System')
        ->not->toContain('081299998888')
        ->not->toContain('Bulk update status');

    $riwayat = rpRiwayat($p, $this);
    expect($riwayat[0])->toHaveKeys(['slug', 'nama', 'icon', 'waktu', 'keterangan', 'lokasi', 'foto'])
        ->not->toHaveKeys(['catatan', 'creator', 'user', 'petugas'])
        ->and($riwayat[0]['keterangan'])->toBe(StatusPengiriman::where('slug', 'produksi')->value('deskripsi'));
});

it('catatan resi dan doa wakaf tidak tampil di halaman lacak, tetapi tetap tercetak di lembar resi', function () {
    $p = rpResi('produksi', ['catatan' => 'Donatur: Tito | Doa: Semoga rezekinya lancar']);
    // Pengiriman::wakafItem() adalah belongsTo lewat pengiriman.wakaf_item_id.
    $item = WakafItem::factory()->create(['pengiriman_id' => $p->id, 'donatur_id' => $p->donatur_id, 'doa_request' => 'Semoga rezekinya lancar']);
    $p->update(['wakaf_item_id' => $item->id]);

    $props = $this->get('/tracking/'.$p->no_resi)->assertOk()->viewData('page')['props'];
    $html = $this->get('/tracking/'.$p->no_resi)->getContent();

    expect($props['pengiriman'])->not->toHaveKey('catatan')
        ->and($props['pengiriman']['wakaf_item'])->not->toHaveKey('doa_request')
        ->and($html)->not->toContain('Semoga rezekinya lancar');

    $this->getJson('/api/tracking/'.$p->no_resi)->assertOk()->assertDontSee('Semoga rezekinya lancar');

    // Lembar cetak resi (admin) tetap memuat doa.
    $admin = User::factory()->create(['is_active' => true]);
    Permission::firstOrCreate(['name' => 'qr.generate', 'guard_name' => 'web']);
    $admin->givePermissionTo('qr.generate');

    $this->actingAs($admin)
        ->get(route('admin.thermal-print.single', $p))
        ->assertOk()
        ->assertSee('Semoga rezekinya lancar');
});

it('selalu menampilkan tahap pemesanan walau resi dibuat tanpa riwayat', function () {
    $p = rpResi('pemesanan');

    expect(array_column(rpRiwayat($p, $this), 'slug'))->toBe(['pemesanan']);
});

it('menggabungkan riwayat status dan tracking pada tahap yang sama tanpa duplikat', function () {
    $p = rpResi('pengiriman');
    rpPindah($p, 'selesai-packing', 'pengiriman', '2026-10-16 10:00:00', [
        'dokumentasi' => ['status_updates/a.jpg'],
    ]);
    TrackingHistory::create([
        'pengiriman_id' => $p->id,
        'status_id' => rpStatus('pengiriman'),
        'user_id' => User::factory()->create()->id,
        'tanggal_update' => '2026-10-16 10:00:00',
        'lokasi' => 'Gudang Semarang',
        'keterangan' => 'Bulk update status',
        'foto_dokumentasi' => ['dokumentasi/b.jpg'],
    ]);

    $tahap = collect(rpRiwayat($p, $this))->where('slug', 'pengiriman')->values();

    expect($tahap)->toHaveCount(1)
        ->and($tahap[0]['lokasi'])->toBe('Gudang Semarang')
        ->and($tahap[0]['foto'])->toHaveCount(2);
});

it('membaca foto dokumentasi lama dalam berbagai format', function (mixed $dokumentasi, int $jumlah) {
    $p = rpResi('produksi');

    DB::table('status_histories')->insert([
        'pengiriman_id' => $p->id,
        'status_from' => rpStatus('pemesanan'),
        'status_to' => rpStatus('produksi'),
        'catatan' => 'x',
        'created_by' => User::factory()->create()->id,
        'dokumentasi' => $dokumentasi,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $produksi = collect(rpRiwayat($p, $this))->firstWhere('slug', 'produksi');

    expect($produksi['foto'])->toHaveCount($jumlah);

    if ($jumlah > 0) {
        expect($produksi['foto'][0])->toContain('storage/');
    }
})->with([
    'larik path' => [json_encode(['status_updates/1.jpg', 'status_updates/2.jpg']), 2],
    'json ganda' => [json_encode(json_encode(['dokumentasi/1.jpg', 'dokumentasi/2.jpg'])), 2],
    'larik objek' => [json_encode([['path' => 'status_updates/t.jpg', 'url' => 'http://localhost/storage/status_updates/t.jpg']]), 1],
    'rusak' => ['"legacy malformed data"', 0],
    'kosong' => [null, 0],
]);

it('menampilkan nama penerima dari serah terima kurir dan konfirmasi diterima', function () {
    $p = rpResi('diterima', ['received_by' => 'Bu Siti']);
    rpPindah($p, 'pengiriman', 'tiba', '2026-10-16 14:00:00');
    rpPindah($p, 'tiba', 'diterima', '2026-10-16 15:00:00');
    SerahTerimaKurir::create([
        'pengiriman_id' => $p->id,
        'user_id' => User::factory()->create()->id,
        'jenis' => SerahTerimaKurir::JENIS_TUJUAN,
        'nama_penerima' => 'Bu Siti',
    ]);

    $riwayat = collect(rpRiwayat($p, $this))->keyBy('slug');

    expect($riwayat['tiba']['keterangan'])->toBe('Diserahkan kepada Bu Siti.')
        ->and($riwayat['diterima']['keterangan'])->toBe('Quran telah diterima oleh Bu Siti.')
        ->and($riwayat->keys()->first())->toBe('diterima');
});

it('menandai penyerahan di tengah jalan tanpa menyebut tujuan awalnya', function () {
    $p = rpResi('tiba');
    rpPindah($p, 'pengiriman', 'tiba', '2026-10-16 14:00:00');
    SerahTerimaKurir::create([
        'pengiriman_id' => $p->id,
        'user_id' => User::factory()->create()->id,
        'jenis' => SerahTerimaKurir::JENIS_TENGAH_JALAN,
        'nama_penerima' => 'Takmir Masjid',
        'nama_lembaga_asal' => 'TPQ Rahasia',
    ]);

    $tiba = collect(rpRiwayat($p, $this))->firstWhere('slug', 'tiba');

    expect($tiba['keterangan'])->toBe('Diserahkan dalam perjalanan kepada Takmir Masjid.')
        ->and($tiba['keterangan'])->not->toContain('TPQ Rahasia');
});

it('API lacak memakai riwayat publik yang sama tanpa nama petugas', function () {
    $p = rpResi('produksi');
    rpPindah($p, 'pemesanan', 'produksi', '2026-10-13 08:00:00');

    $this->getJson('/api/tracking/'.$p->no_resi)
        ->assertOk()
        ->assertJsonPath('data.riwayat.0.slug', 'produksi')
        ->assertJsonMissingPath('data.status_history')
        ->assertJsonMissingPath('data.tracking_history')
        ->assertDontSee('Staff Gudang');
});

it('tetap cepat dengan banyak riwayat', function () {
    $p = rpResi('selesai-packing');

    foreach (range(1, 40) as $i) {
        rpPindah($p, 'produksi', ['produksi', 'packing', 'selesai-packing'][$i % 3], now()->subMinutes(100 - $i)->toDateTimeString());
    }

    $mulai = microtime(true);
    $riwayat = rpRiwayat($p, $this);

    expect(microtime(true) - $mulai)->toBeLessThan(1.0)
        ->and(array_column($riwayat, 'slug'))->toBe(['selesai-packing', 'packing', 'produksi', 'pemesanan']);
});
