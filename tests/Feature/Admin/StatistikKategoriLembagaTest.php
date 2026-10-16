<?php

use App\Models\MushafRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Kartu "Statistik Distribusi berdasarkan Kategori Lembaga" di halaman daftar
 * permintaan mushaf.
 *
 * Dua cacat yang dijaga di sini, keduanya senyap — kartunya tampil rapi, hanya
 * isinya yang salah:
 *
 *   1. Angkanya dijumlahkan dari `mushafRequests.data`, yaitu HALAMAN yang
 *      sedang dibuka. Daftarnya diurutkan terbaru dulu sementara yang berstatus
 *      selesai justru yang paling lama, jadi selama halaman pertama belum
 *      memuat satu pun permintaan selesai, keempat kartu menampilkan 0.
 *   2. Peta sub-kategori → grup disalin ke frontend dan menjadi basi: "Lembaga
 *      Lainnya", "Panti Asuhan/Anak Yatim", dan "Muallaf" tidak ada di salinan
 *      itu, sehingga permintaannya jatuh ke "Tidak Dikategorikan".
 */
beforeEach(function () {
    $role = Role::firstOrCreate(['name' => 'super-admin']);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole($role);

    Permission::firstOrCreate(['name' => 'mushaf-requests.read']);
    $role->givePermissionTo('mushaf-requests.read');
});

it('menghitung seluruh permintaan selesai, bukan hanya halaman yang sedang dibuka', function () {
    // Tiga puluh permintaan baru yang belum selesai mendorong yang selesai ke
    // halaman berikutnya.
    MushafRequest::factory()->count(30)->create(['status' => 'pending']);

    // Kolom `*_approved` sengaja TIDAK diisi: hook `saving` hanya mengisi
    // `jumlah_mushaf_approved` bila kolom pecahannya berubah, dan selama kolom
    // itu null jumlah yang ditampilkan adalah jumlah yang diajukan.
    $selesai = MushafRequest::factory()->completed()->create([
        'kategori_lembaga' => 'Lembaga Lainnya',
        'jumlah_mushaf_a5' => 10,
        'jumlah_mushaf_a6' => 0,
        'jumlah_iqra' => 5,
    ]);

    // `created_at` tidak masuk `$fillable`, jadi nilai dari factory tidak
    // terpakai dan seluruh baris akan bertanggal sama — urutannya jadi acak.
    // Diisi langsung supaya permintaan selesai benar-benar berada di belakang.
    $selesai->created_at = now()->subMonth();
    $selesai->save();

    // Halaman pertama benar-benar tidak memuat satu pun permintaan selesai —
    // inilah keadaan yang dulu membuat semua kartu bernilai nol.
    $halamanPertama = MushafRequest::orderByDesc('created_at')->paginate(20);
    expect(collect($halamanPertama->items())->where('status', 'completed'))->toBeEmpty();

    $this->actingAs($this->admin)
        ->get('/admin/mushaf-requests')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/MushafRequest/Index')
            ->where('kategoriStats.Lembaga Sosial & Pemerintahan.count', 1)
            ->where('kategoriStats.Lembaga Sosial & Pemerintahan.totalMushaf', 10)
            ->where('kategoriStats.Lembaga Sosial & Pemerintahan.totalIqra', 5)
            ->where('kategoriStats.Lembaga Pendidikan & Pembinaan.count', 0)
        );
});

it('selalu menyertakan keempat grup walau belum ada yang selesai', function () {
    MushafRequest::factory()->count(3)->create(['status' => 'pending']);

    $this->actingAs($this->admin)
        ->get('/admin/mushaf-requests')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('kategoriStats')
            ->where('kategoriStats.Lembaga Pendidikan & Pembinaan.count', 0)
            ->where('kategoriStats.Komunitas & Dakwah Kemasyarakatan.count', 0)
            ->where('kategoriStats.Lembaga Sosial & Pemerintahan.count', 0)
            ->where('kategoriStats.Penerima Manfaat Khusus.count', 0)
        );
});

it('mengelompokkan kategori yang tidak ada di salinan peta frontend lama', function () {
    MushafRequest::factory()->completed()->create(['kategori_lembaga' => 'Lembaga Lainnya']);
    MushafRequest::factory()->completed()->create(['kategori_lembaga' => 'Muallaf']);
    MushafRequest::factory()->completed()->create(['kategori_lembaga' => 'Panti Asuhan/Anak Yatim']);

    $statistik = MushafRequest::statistikGrupKategori();

    expect($statistik['Lembaga Sosial & Pemerintahan']['count'])->toBe(2)
        ->and($statistik['Penerima Manfaat Khusus']['count'])->toBe(1)
        // Tidak ada yang jatuh ke keranjang tak berkategori.
        ->and($statistik)->not->toHaveKey('Tidak Dikategorikan');
});

it('mengabaikan permintaan yang belum selesai', function () {
    MushafRequest::factory()->completed()->create(['kategori_lembaga' => 'Yayasan']);
    MushafRequest::factory()->create(['status' => 'approved', 'kategori_lembaga' => 'Yayasan']);

    $statistik = MushafRequest::statistikGrupKategori();

    expect($statistik['Lembaga Sosial & Pemerintahan']['count'])->toBe(1);
});
