<?php

use App\Models\Donatur;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Models\WakafItem;
use Database\Seeders\JenisQuranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Mengukur apa yang BENAR-BENAR hilang ketika tombol Hapus donatur ditekan.
 *
 * Ini bukan menguji fitur baru, melainkan merekam perilaku yang ada sekarang, supaya
 * perubahan berikutnya punya dasar yang terukur.
 *
 * Perhatian: hapus hanya diblokir kalau ADA pengiriman yang statusnya BUKAN "Batal".
 * Begitu seluruh pengiriman dibatalkan, donaturnya bisa dihapus — dan penghapusan itu
 * menghapus batch, item wakaf, pengiriman, serta sertifikatnya sekaligus.
 */
beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    foreach (['dashboard.view', 'users.read', 'donatur.read', 'donatur.create', 'donatur.update', 'donatur.delete'] as $izin) {
        Permission::firstOrCreate(['name' => $izin, 'guard_name' => 'web']);
    }
    Role::findByName('super-admin')->syncPermissions(Permission::all());
    $this->seed(JenisQuranSeeder::class);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('super-admin');

    $this->batal = StatusPengiriman::where('slug', 'batal')->firstOrFail();
    $this->proses = StatusPengiriman::where('slug', 'pemesanan')->firstOrFail();
});

it('MENOLAK hapus selama masih ada pengiriman yang belum Batal', function () {
    $donatur = Donatur::factory()->create(['created_by' => $this->admin->id]);
    $item = WakafItem::factory()->create(['donatur_id' => $donatur->id, 'status' => 'pending']);
    $kirim = Pengiriman::factory()->create([
        'donatur_id' => $donatur->id,
        'status_id' => $this->proses->id,
    ]);

    // Relasinya lewat wakaf_items.pengiriman_id, bukan pengiriman.wakaf_item_id.
    $item->update(['pengiriman_id' => $kirim->id]);

    $this->actingAs($this->admin)
        ->delete('/admin/donatur/'.$donatur->id)
        ->assertSessionHasErrors('error');

    expect(Donatur::find($donatur->id))->not->toBeNull();
});

it('MELOLOSKAN hapus begitu semua pengiriman sudah Batal — dan menghapus semuanya', function () {
    $donatur = Donatur::factory()->create([
        'created_by' => $this->admin->id,
        'donation_count' => 3,
        'total_a5_count' => 3,
    ]);

    // Tiga resi, semuanya sudah ditandai Batal oleh staf.
    foreach ([1, 2, 3] as $n) {
        $kirim = Pengiriman::factory()->create([
            'donatur_id' => $donatur->id,
            'status_id' => $this->batal->id,
        ]);
        WakafItem::factory()->create([
            'donatur_id' => $donatur->id,
            'pengiriman_id' => $kirim->id,
            'wakaf_type' => 'A5',
            'sequence_in_type' => $n,
            'global_sequence' => $n,
            'status' => 'pending',
        ]);
    }

    $idSebelum = $donatur->id;

    // Inilah yang dicatat sebelum penghapusan, supaya terlihat berapa yang hilang.
    $sebelum = [
        'donatur' => Donatur::where('id', $idSebelum)->count(),
        'wakaf_items' => WakafItem::where('donatur_id', $idSebelum)->count(),
        'pengiriman' => Pengiriman::where('donatur_id', $idSebelum)->count(),
    ];

    $this->actingAs($this->admin)
        ->delete('/admin/donatur/'.$idSebelum)
        ->assertSessionHasNoErrors();

    $sesudah = [
        'donatur' => Donatur::where('id', $idSebelum)->count(),
        'wakaf_items' => WakafItem::where('donatur_id', $idSebelum)->count(),
        'pengiriman' => Pengiriman::where('donatur_id', $idSebelum)->count(),
    ];

    dump('SEBELUM dihapus', $sebelum);
    dump('SESUDAH dihapus', $sesudah);

    // Terbukti hilang seluruhnya, tanpa sisa dan tanpa arsip.
    expect($sesudah['donatur'])->toBe(0);
    expect($sesudah['wakaf_items'])->toBe(0);
    expect($sesudah['pengiriman'])->toBe(0);
});

it('tidak menyisakan arsip apa pun yang bisa dipulihkan', function () {
    $donatur = Donatur::factory()->create([
        'created_by' => $this->admin->id,
        'kode_donatur' => 'ARSIP-UJI-1',
        'nama_donatur' => 'Uji Arsip',
    ]);

    $this->actingAs($this->admin)->delete('/admin/donatur/'.$donatur->id);

    // Tidak ada kolom deleted_at, tidak ada tabel arsip — kode itu lenyap dari riwayat.
    expect(Schema::hasColumn('donatur', 'deleted_at'))->toBeFalse();
    expect(Donatur::where('kode_donatur', 'ARSIP-UJI-1')->count())->toBe(0);
});

it('membiarkan donatur yang punya pengiriman Batal DAN aktif tetap aman', function () {
    // Campuran: satu Batal, satu masih berjalan. Harus ditolak — jangan sampai
    // pembatalan sebagian membuka jalan penghapusan bagi sisanya.
    $donatur = Donatur::factory()->create(['created_by' => $this->admin->id]);

    $i1 = WakafItem::factory()->create(['donatur_id' => $donatur->id, 'status' => 'pending']);
    $k1 = Pengiriman::factory()->create(['donatur_id' => $donatur->id, 'status_id' => $this->batal->id]);
    $i1->update(['pengiriman_id' => $k1->id]);

    $i2 = WakafItem::factory()->create(['donatur_id' => $donatur->id, 'status' => 'pending']);
    $k2 = Pengiriman::factory()->create(['donatur_id' => $donatur->id, 'status_id' => $this->proses->id]);
    $i2->update(['pengiriman_id' => $k2->id]);

    $this->actingAs($this->admin)
        ->delete('/admin/donatur/'.$donatur->id)
        ->assertSessionHasErrors('error');

    expect(Donatur::find($donatur->id))->not->toBeNull();
    expect(WakafItem::where('donatur_id', $donatur->id)->count())->toBe(2);
});

it('MENOLAK hapus bila kode donatur yang diketik tidak cocok', function () {
    // Pagar sisi server: halaman sudah meminta kode donaturnya diketik, tapi permintaan
    // bisa dikirim langsung tanpa lewat halaman. Tanpa pemeriksaan ini, pagar di layar
    // bisa dilewati begitu saja.
    $donatur = Donatur::factory()->create([
        'created_by' => $this->admin->id,
        'kode_donatur' => 'PAGAR-01',
    ]);

    $this->actingAs($this->admin)
        ->delete('/admin/donatur/'.$donatur->id.'?konfirmasi_kode=SALAH')
        ->assertSessionHasErrors('error');

    expect(Donatur::find($donatur->id))->not->toBeNull();
});

it('MELOLOSKAN hapus bila kode donatur yang diketik cocok', function () {
    $donatur = Donatur::factory()->create([
        'created_by' => $this->admin->id,
        'kode_donatur' => 'PAGAR-02',
    ]);

    $this->actingAs($this->admin)
        ->delete('/admin/donatur/'.$donatur->id.'?konfirmasi_kode=pagar-02')
        ->assertSessionHasNoErrors();

    // Cocok walau beda huruf besar/kecil, karena kode donatur tidak membedakannya.
    expect(Donatur::find($donatur->id))->toBeNull();
});
