<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Fase 3 revisi (13/10/2026): satuan stok berubah dari DUS utuh ke EKS.
 *
 * Alasan: pemilik sistem minta "fix kalau mushafnya tidak genap dalam satu
 * dus" — sisa 12 eks A5 tidak bisa dicatat bila kolomnya memaksa dus bulat.
 * Eks jadi satuan dasar; DUS tetap ditampilkan di UI sebagai penjumlahan
 * (1 dus A5 = 20 eks, A6 = 40, IQRO = 160 — BoxUnits).
 *
 * Ketiga tabel ini masih KOSONG di produksi dan di DB uji (fitur baru), jadi
 * rename aman; kalau suatu saat ada isinya, migrasi ini TIDAK mengkonversi
 * nilainya — berhenti dan minta konversi manual dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        $gagal = collect([
            'stok_mushaf',
            'stok_mushaf_pergerakan',
            'transfer_stok',
        ])->filter(fn ($tabel) => DB::table($tabel)->count() > 0);

        if ($gagal->isNotEmpty()) {
            throw new RuntimeException('Tabel stok sudah terisi: '.implode(', ', $gagal->all()).'. Konversi dus→eks manual dulu sebelum migrasi.');
        }

        Schema::table('stok_mushaf', fn ($t) => $t->renameColumn('jumlah_dus', 'jumlah_eks'));
        Schema::table('stok_mushaf_pergerakan', fn ($t) => $t->renameColumn('jumlah_dus', 'jumlah_eks'));
        Schema::table('transfer_stok', fn ($t) => $t->renameColumn('jumlah_dus', 'jumlah_eks'));
    }

    public function down(): void
    {
        Schema::table('stok_mushaf', fn ($t) => $t->renameColumn('jumlah_eks', 'jumlah_dus'));
        Schema::table('stok_mushaf_pergerakan', fn ($t) => $t->renameColumn('jumlah_eks', 'jumlah_dus'));
        Schema::table('transfer_stok', fn ($t) => $t->renameColumn('jumlah_eks', 'jumlah_dus'));
    }
};
