<?php

use App\Models\JenisQuran;
use Database\Seeders\JenisQuranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Isi 1 doz (= 1 kerdus penuh) berbeda per ukuran Quran, dan angka itulah yang
 * dipakai halaman Pelacakan Kerdus untuk menyebut isi kerdus dalam doz:
 *   A5 = 20, A6 = 40, IQRO = 160.
 *
 * Pernah rusak di produksi: JenisQuranSeeder memakai updateOrCreate tanpa
 * menyebut `default_capacity`, sehingga setiap kali seeder dijalankan kolom itu
 * kembali ke default skema (20) — A6 dan IQRO ikut jadi 20. Tidak ada error
 * yang muncul; halaman tetap tampil, hanya hitungan doz-nya salah.
 */
it('menetapkan isi doz sesuai ukuran setelah seeder dijalankan', function () {
    $this->seed(JenisQuranSeeder::class);

    expect(JenisQuran::where('kode_jenis', 'A5')->value('default_capacity'))->toBe(20)
        ->and(JenisQuran::where('kode_jenis', 'A6')->value('default_capacity'))->toBe(40)
        ->and(JenisQuran::where('kode_jenis', 'IQRO')->value('default_capacity'))->toBe(160);
});

it('tidak menurunkan isi doz bila seeder dijalankan berulang kali', function () {
    // Inilah bentuk kegagalan aslinya: seeding ulang menimpa nilai yang benar.
    $this->seed(JenisQuranSeeder::class);
    $before = JenisQuran::where('kode_jenis', 'IQRO')->value('default_capacity');

    $this->seed(JenisQuranSeeder::class);

    expect(JenisQuran::where('kode_jenis', 'IQRO')->value('default_capacity'))->toBe($before)
        ->and($before)->toBe(160);
});

it('menyebut isi doz lewat model dengan benar', function () {
    $this->seed(JenisQuranSeeder::class);

    expect(JenisQuran::where('kode_jenis', 'A5')->first()->getDefaultCapacity())->toBe(20)
        ->and(JenisQuran::where('kode_jenis', 'A6')->first()->getDefaultCapacity())->toBe(40)
        ->and(JenisQuran::where('kode_jenis', 'IQRO')->first()->getDefaultCapacity())->toBe(160);
});
