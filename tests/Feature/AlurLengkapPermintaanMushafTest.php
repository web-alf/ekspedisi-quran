<?php

use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Models\Sertifikat;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Models\WakafBatch;
use Database\Seeders\JenisQuranSeeder;
use Database\Seeders\StatusPengirimanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Simulasi alur permintaan mushaf dari PENGajuan publik sampai penerbitan
 * sertifikat — dijalankan menembus rute dan controller yang sebenarnya, bukan
 * memanggil model langsung. Tujuannya dua:
 *
 *   1. Membuktikan rantainya utuh untuk donatur yang disiapkan sebagaimana
 *      mestinya (punya batch wakaf).
 *   2. Menirukan keadaaan donatur di produksi (punya item wakaf tetapi TIDAK
 *      punya batch) untuk melihat persis di tahap mana rantai itu putus.
 */
beforeEach(function () {
    $role = Role::firstOrCreate(['name' => 'super-admin']);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole($role);

    $izin = [
        'mushaf-requests.view', 'mushaf-requests.update', 'mushaf-requests.approve',
        'mushaf-requests.process', 'shipments.view', 'shipments.update-status',
        'qr.generate', 'donatur.view',
    ];

    foreach ($izin as $nama) {
        Permission::firstOrCreate(['name' => $nama]);
    }

    $role->givePermissionTo($izin);

    $this->seed(JenisQuranSeeder::class);
    $this->seed(StatusPengirimanSeeder::class);
});

/**
 * Jalankan seluruh alur dan kembalikan jejak tiap tahap.
 *
 * @return array{jejak: array<int, array{tahap: string, hasil: string}>, permintaan: MushafRequest, pengiriman: Pengiriman}
 */
function jalankanAlurPermintaanMushaf($test, ?Donatur $donatur = null): array
{
    $jejak = [];
    $catat = function (string $tahap, string $hasil) use (&$jejak): void {
        $jejak[] = ['tahap' => $tahap, 'hasil' => $hasil];
    };

    // ---------- TAHAP 1: publik mengajukan lewat formulir terbuka ----------
    $test->post('/mushaf-request', [
        'nama_lembaga' => 'TPQ Uji Simulasi',
        'kategori_lembaga' => collect(MushafRequest::KATEGORI_LEMBAGA)->flatten()->first(),
        'alamat_lengkap' => 'Jl. Uji Simulasi No. 1',
        'latitude' => -6.2607,
        'longitude' => 106.7816,
        'provinsi' => 'DKI Jakarta',
        'kota_kabupaten' => 'Jakarta Selatan',
        'nama_pengurus_1' => 'Ust. Ahmad',
        'jabatan_pengurus_1' => 'Ketua',
        'whatsapp_pengurus_1' => '081234567890',
        'nama_pengurus_2' => 'Ust. Bagir',
        'jabatan_pengurus_2' => 'Sekretaris',
        'whatsapp_pengurus_2' => '081298765432',
        'urgensi_request' => 'Untuk kegiatan belajar santri',
        'jumlah_mushaf' => 100,
        'jumlah_mushaf_a5' => 100,
        'jumlah_mushaf_a6' => 0,
        'jumlah_iqra' => 0,
        'jenis_mushaf_diminta' => ['A5'],
        'sumber_info' => 'Media sosial',
        'foto_santri' => UploadedFile::fake()->image('santri.jpg'),
        'foto_lembaga' => UploadedFile::fake()->image('lembaga.jpg'),
        'file_nama_santri' => UploadedFile::fake()->create('nama-santri.xlsx', 10),
    ])->assertRedirect();

    $permintaan = MushafRequest::latest('id')->firstOrFail();
    $catat('01 publik mengajukan', $permintaan->no_request.' · status='.$permintaan->status);

    $test->actingAs($test->admin);

    // ---------- TAHAP 2: CS menelaah ----------
    $test->patch("/admin/mushaf-requests/{$permintaan->id}/status", ['status' => 'reviewed'])
        ->assertRedirect();
    $catat('02 CS menelaah', 'status='.$permintaan->fresh()->status);

    // ---------- TAHAP 3: CS menetapkan jumlah yang disetujui ----------
    // Sengaja kecil: sejak satu mushaf = satu resi (15/10/2026), jumlah ini sama dengan
    // BANYAKNYA kiriman, dan tiap kiriman harus ditempuh satu per satu sampai diterima.
    // Angka besar hanya memperlambat uji tanpa menguji hal yang berbeda.
    $test->patch("/admin/mushaf-requests/{$permintaan->id}/quantities", [
        'jumlah_mushaf_a5_approved' => 4,
        'jumlah_mushaf_a6_approved' => 0,
        'jumlah_iqra_approved' => 0,
    ])->assertRedirect();
    $permintaan->refresh();
    $catat('03 jumlah disetujui', 'total='.$permintaan->approved_breakdown['total']);

    // ---------- TAHAP 4: CS menyetujui permintaan ----------
    $test->patch("/admin/mushaf-requests/{$permintaan->id}/status", ['status' => 'approved'])
        ->assertRedirect();
    $permintaan->refresh();
    $catat('04 menyetujui', 'status='.$permintaan->status.' · approved_by='.$permintaan->reviewed_by);

    // ---------- TAHAP 5: admin memproses ke pengiriman ----------
    $donatur ??= Donatur::factory()->create(['nama_donatur' => 'Donatur Uji']);

    $test->post("/admin/mushaf-requests/{$permintaan->id}/process", [
        'donatur_id' => $donatur->id,
        'tanggal_wakaf' => now()->format('Y-m-d'),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $permintaan->refresh();
    $pengiriman = Pengiriman::find($permintaan->pengiriman_id);
    $catat('05 proses ke pengiriman', ($pengiriman ? $pengiriman->no_resi : 'GAGAL').' · status permintaan='.$permintaan->status);
    $catat('   taut dua arah', 'pengiriman.mushaf_request_id='.($pengiriman?->mushaf_request_id ?? 'null')
        .' · batch kiriman='.($pengiriman?->wakaf_batch_id ?? 'kosong'));
    $catat('   batch milik donatur', WakafBatch::where('donatur_id', $donatur->id)->count()
        .' batch · id donatur kiriman='.($pengiriman?->donatur_id ?? 'null'));

    if (! $pengiriman) {
        return compact('jejak', 'permintaan') + ['pengiriman' => null];
    }

    // ---------- TAHAP 6: QR resi dibuat ----------
    $test->post("/admin/qr/generate/{$pengiriman->id}");
    $pengiriman->refresh();
    $catat('06 QR dibuat', $pengiriman->qr_code_path ? 'qr_code_path terisi' : 'qr_code_path KOSONG');

    // ---------- TAHAP 7: tiap kiriman menempuh 7 tahapan, satu langkah tiap kali ----------
    //
    // Satu mushaf = satu resi (15/10/2026): satu permintaan punya BANYAK kiriman, dan
    // permintaan baru menutup setelah SELURUH kirimannya diterima. Karena itu SEMUA kiriman
    // ditempuh sampai selesai di sini — persis seperti operator yang memindai satu per satu.
    // 'tiba' (Tiba di Tujuan, 16/10/2026) wajib dilewati sebelum 'diterima'.
    $tahapan = ['produksi', 'kedatangan', 'packing', 'selesai-packing', 'pengiriman', 'tiba', 'diterima'];
    $idTahapan = StatusPengiriman::whereIn('slug', $tahapan)->pluck('id', 'slug');
    $semuaResi = Pengiriman::where('mushaf_request_id', $permintaan->id)->orderBy('id')->get();

    foreach ($semuaResi as $urutan => $resiIni) {
        foreach ($tahapan as $slug) {
            if (! isset($idTahapan[$slug])) {
                $catat('07 status '.$slug, 'SKIP: status tidak ada di master');

                continue;
            }

            $test->post("/admin/pengiriman/{$resiIni->id}/update-status", ['status_id' => $idTahapan[$slug]]);
            $resiIni->refresh();

            if ($urutan === 0) {
                $catat('07 status → '.$slug, 'status sekarang='.($resiIni->status?->slug ?? '?'));
            }
        }
    }

    $catat('07b seluruh kiriman', $semuaResi->count().' kiriman berisi 1 eks → diterima (permintaan ini berisi '
        .$permintaan->approved_breakdown['total'].' eks)');

    // ---------- TAHAP 8: permintaan seharusnya menutup sendiri ----------
    $permintaan->refresh();
    $catat('08 permintaan menutup sendiri', 'status='.$permintaan->status);

    // ---------- TAHAP 9: batch ditutup & sertifikat disiapkan ----------
    $test->artisan('wakaf:tutup-pengiriman');

    $sertifikat = Sertifikat::where('donatur_id', $donatur->id)->get();
    $batch = WakafBatch::find($pengiriman->fresh()->wakaf_batch_id);
    $catat('09 batch & sertifikat', 'batch='.($batch?->batch_code ?? 'TIDAK ADA')
        .' · batch.status='.($batch?->status ?? '-')
        .' · sertifikat='.$sertifikat->count()
        .($sertifikat->isNotEmpty() ? ' ('.$sertifikat->first()->nomor_sertifikat.')' : ''));

    return ['jejak' => $jejak, 'permintaan' => $permintaan, 'pengiriman' => $pengiriman];
}

function tulisJejak(array $jejak): void
{
    $baris = ['', '  TAHAP                                     HASIL'];
    foreach ($jejak as $langkah) {
        $tahap = $langkah['tahap'] ?? $langkah[0];
        $hasil = $langkah['hasil'] ?? $langkah[1];
        $baris[] = '  '.str_pad($tahap, 40).' '.$hasil;
    }

    fwrite(STDERR, implode(PHP_EOL, $baris).PHP_EOL);
}

it('menuntaskan alur untuk donatur yang punya batch wakaf', function () {
    $donatur = Donatur::factory()->create(['nama_donatur' => 'Donatur Uji']);

    WakafBatch::factory()->create([
        'donatur_id' => $donatur->id,
        'jenis_quran_id' => JenisQuran::where('kode_jenis', 'A5')->value('id'),
        'status' => 'pending_distribution',
    ]);

    $hasil = jalankanAlurPermintaanMushaf($this, $donatur);
    tulisJejak($hasil['jejak']);

    expect($hasil['pengiriman'])->not->toBeNull()
        ->and($hasil['permintaan']->status)->toBe('completed');

    tulisJejak([['RINGKASAN', 'sertifikat terbit: '.Sertifikat::count()]]);
    expect(Sertifikat::count())->toBeGreaterThan(0);
});

it('menirukan donatur produksi yang punya item tetapi tanpa batch', function () {
    $donatur = Donatur::factory()->create(['nama_donatur' => 'Donatur Tanpa Batch']);

    $hasil = jalankanAlurPermintaanMushaf($this, $donatur);
    tulisJejak($hasil['jejak']);

    expect($hasil['pengiriman'])->not->toBeNull()
        ->and($hasil['permintaan']->status)->toBe('completed');

    tulisJejak([['RINGKASAN', 'sertifikat terbit: '.Sertifikat::count()]]);
    expect(Sertifikat::count())->toBe(0);
});
