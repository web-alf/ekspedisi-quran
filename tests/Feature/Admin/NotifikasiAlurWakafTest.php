<?php

use App\Models\Donatur;
use App\Models\Pengiriman;
use App\Models\Sertifikat;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Models\WakafBatch;
use App\Models\WhatsAppNotification;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

uses(RefreshDatabase::class);

/**
 * Sambungan notifikasi ke alur kerja.
 *
 * Dua titik sambung yang diuji, keduanya menyelesaikan butir yang sebelumnya
 * menggantung di diagram alur:
 *   - resi masuk 'diterima'  -> donatur dikabari mushafnya sudah sampai;
 *   - batch ditutup          -> donatur dikabari sertifikatnya siap.
 *
 * Yang paling penting diuji di sini bukan "pesannya terkirim", tapi bahwa
 * notifikasi TIDAK BISA merusak alur utama: staf gudang harus tetap bisa
 * memindahkan status resi walau notifikasi sedang rusak.
 */

/** Nama helper sengaja diawali `waAlur` — Pest memuat semua berkas uji sekaligus. */
function waAlurStatusId(string $slug): int
{
    return (int) StatusPengiriman::query()->where('slug', $slug)->value('id');
}

function waAlurSiapkanStatus(): void
{
    foreach (['pemesanan', 'produksi', 'kedatangan', 'packing', 'selesai-packing', 'pengiriman', 'diterima'] as $i => $slug) {
        StatusPengiriman::firstOrCreate(
            ['slug' => $slug],
            ['nama' => ucfirst(str_replace('-', ' ', $slug)), 'urutan' => $i + 1, 'is_active' => true, 'is_final' => $slug === 'diterima']
        );
    }
}

beforeEach(function () {
    User::factory()->create();
    waAlurSiapkanStatus();
    WhatsAppSetting::factory()->create();
});

it('mengabari donatur saat resinya masuk ke diterima', function () {
    Queue::fake();
    WhatsAppTemplate::factory()->create([
        'name' => 'resi_diterima',
        'content' => 'Halo {nama}, resi {resi} sudah sampai.',
    ]);

    $donatur = Donatur::factory()->create(['nama_donatur' => 'Ahmad Fauzi', 'no_hp' => '08123456789']);
    $resi = Pengiriman::factory()->create([
        'donatur_id' => $donatur->id,
        'status_id' => waAlurStatusId('pemesanan'),
    ]);

    $resi->updateStatus(waAlurStatusId('diterima'));

    $notifikasi = WhatsAppNotification::query()->where('event_key', 'resi_diterima')->first();

    expect($notifikasi)->not->toBeNull()
        ->and($notifikasi->status)->toBe('menunggu')
        ->and($notifikasi->recipient)->toBe('08123456789')
        ->and($notifikasi->body)->toBe('Halo Ahmad Fauzi, resi '.$resi->no_resi.' sudah sampai.')
        ->and($notifikasi->pengiriman_id)->toBe($resi->id)
        ->and($notifikasi->donatur_id)->toBe($donatur->id);
});

it('tidak mengabari donatur lagi saat resi diterima disimpan ulang', function () {
    Queue::fake();
    WhatsAppTemplate::factory()->create(['name' => 'resi_diterima']);

    $donatur = Donatur::factory()->create();
    $resi = Pengiriman::factory()->create([
        'donatur_id' => $donatur->id,
        'status_id' => waAlurStatusId('pemesanan'),
    ]);

    $resi->updateStatus(waAlurStatusId('diterima'));
    // Simpan ulang tanpa mengubah status: ini yang memicu pengiriman ganda
    // kalau kabarnya tidak dibatasi pada perubahan status.
    $resi->update(['nama_penerima' => 'Penerima Baru']);

    expect(WhatsAppNotification::query()->where('event_key', 'resi_diterima')->count())->toBe(1);
});

it('tidak mengabari donatur untuk status selain diterima', function () {
    Queue::fake();
    WhatsAppTemplate::factory()->create(['name' => 'resi_diterima']);

    $resi = Pengiriman::factory()->create([
        'donatur_id' => Donatur::factory()->create()->id,
        'status_id' => waAlurStatusId('pemesanan'),
    ]);

    $resi->updateStatus(waAlurStatusId('pengiriman'));

    expect(WhatsAppNotification::query()->count())->toBe(0);
});

it('tetap menyelesaikan perubahan status walau penyusunan pesan gagal', function () {
    // Meniru kerusakan di dalam notifier (galat database, template rusak, dsb).
    // Kontraknya: perubahan status resi oleh staf gudang TIDAK BOLEH gagal.
    $this->mock(WhatsAppTemplateService::class, function ($mock) {
        $mock->shouldReceive('susun')->andThrow(new RuntimeException('template rusak'));
    });

    WhatsAppTemplate::factory()->create(['name' => 'resi_diterima']);

    $donatur = Donatur::factory()->create();
    $resi = Pengiriman::factory()->create([
        'donatur_id' => $donatur->id,
        'status_id' => waAlurStatusId('pemesanan'),
    ]);

    $resi->updateStatus(waAlurStatusId('diterima'));

    // Perubahan statusnya tetap berhasil...
    expect($resi->fresh()->status_id)->toBe(waAlurStatusId('diterima'))
        ->and($resi->fresh()->received_at)->not->toBeNull()
        // ...dan kegagalan notifikasinya tidak meninggalkan baris separuh jadi.
        ->and(WhatsAppNotification::query()->count())->toBe(0);
});

it('mengabari donatur bahwa sertifikatnya siap setelah batchnya ditutup', function () {
    Queue::fake();
    WhatsAppTemplate::factory()->create([
        'name' => 'sertifikat_siap',
        'content' => 'Halo {nama}, batch {batch} selesai. Unduh: {link}',
    ]);

    $donatur = Donatur::factory()->create(['nama_donatur' => 'Ahmad Fauzi', 'no_hp' => '08123456789']);
    $batch = WakafBatch::factory()->create(['donatur_id' => $donatur->id, 'status' => 'pending_distribution']);
    $resi = Pengiriman::factory()->create([
        'donatur_id' => $donatur->id,
        'wakaf_batch_id' => $batch->id,
        'status_id' => waAlurStatusId('diterima'),
    ]);

    $this->artisan('wakaf:tutup-pengiriman')->assertSuccessful();

    $notifikasi = WhatsAppNotification::query()->where('event_key', 'sertifikat_siap')->first();
    $sertifikat = Sertifikat::query()->where('donatur_id', $donatur->id)->first();

    expect($sertifikat)->not->toBeNull()
        ->and($notifikasi)->not->toBeNull()
        ->and($notifikasi->body)->toContain('batch '.$batch->batch_code);

    // Tautannya harus menuju halaman pelacakan yang tidak kedaluwarsa, BUKAN
    // tautan unduh sertifikat langsung yang tokennya mati dalam 24 jam.
    expect($notifikasi->body)->toContain('/tracking/'.$resi->no_resi)
        ->and($notifikasi->body)->not->toContain('/certificate/download/');
});

it('tidak mengabari donatur saat perintah penutup dijalankan dalam mode kering', function () {
    Queue::fake();
    WhatsAppTemplate::factory()->create(['name' => 'sertifikat_siap']);

    $donatur = Donatur::factory()->create();
    $batch = WakafBatch::factory()->create(['donatur_id' => $donatur->id, 'status' => 'pending_distribution']);
    Pengiriman::factory()->create([
        'donatur_id' => $donatur->id,
        'wakaf_batch_id' => $batch->id,
        'status_id' => waAlurStatusId('diterima'),
    ]);

    $this->artisan('wakaf:tutup-pengiriman', ['--kering' => true])->assertSuccessful();

    expect(WhatsAppNotification::query()->count())->toBe(0);
});

it('menampilkan sertifikat konsolidasi di halaman pelacakan donatur', function () {
    $donatur = Donatur::factory()->create(['nama_donatur' => 'Ahmad Fauzi']);
    $batch = WakafBatch::factory()->create(['donatur_id' => $donatur->id, 'status' => 'pending_distribution']);
    $resi = Pengiriman::factory()->create([
        'donatur_id' => $donatur->id,
        'wakaf_batch_id' => $batch->id,
        'status_id' => waAlurStatusId('diterima'),
    ]);

    $this->artisan('wakaf:tutup-pengiriman')->assertSuccessful();

    $sertifikat = Sertifikat::query()->where('donatur_id', $donatur->id)->first();

    // Sertifikat konsolidasi TIDAK tertaut ke batch (wakaf_batch_id NULL), jadi
    // tidak akan ditemukan lewat relasi batch — hanya blok khusus di
    // TrackingController yang bisa menampilkannya. Tanpa itu sertifikatnya ada
    // di database tapi tidak bisa ditemukan donatur di mana pun.
    expect($sertifikat->wakaf_batch_id)->toBeNull()
        ->and($sertifikat->is_consolidated)->toBeTrue();

    $this->get(route('public.tracking', ['no_resi' => $resi->no_resi]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/TrackingResult')
            ->has('certificateUrls', 1)
            ->where('certificateUrls.0.nomor_sertifikat', $sertifikat->nomor_sertifikat)
        );
});

it('tidak menggandakan sertifikat di halaman pelacakan bila batchnya ikut tertaut', function () {
    $donatur = Donatur::factory()->create();
    $batch = WakafBatch::factory()->create(['donatur_id' => $donatur->id, 'status' => 'pending_distribution']);
    $resi = Pengiriman::factory()->create([
        'donatur_id' => $donatur->id,
        'wakaf_batch_id' => $batch->id,
        'status_id' => waAlurStatusId('diterima'),
    ]);

    $this->artisan('wakaf:tutup-pengiriman')->assertSuccessful();

    $sertifikat = Sertifikat::query()->where('donatur_id', $donatur->id)->first();

    // Satu baris yang terjangkau DUA jalur sekaligus: relasi batch
    // (WakafBatch::sertifikat memakai wakaf_batch_id) DAN blok konsolidasi
    // (memakai is_consolidated). Tanpa penyaring nomor, sertifikat yang sama
    // muncul dua kali di halaman pelacakan donatur.
    $sertifikat->update(['wakaf_batch_id' => $batch->id]);

    expect($sertifikat->fresh()->is_consolidated)->toBeTrue();

    $this->get(route('public.tracking', ['no_resi' => $resi->no_resi]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('certificateUrls', 1)
            ->where('certificateUrls.0.nomor_sertifikat', $sertifikat->nomor_sertifikat)
        );
});
