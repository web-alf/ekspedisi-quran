<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Jaring pengaman: satu donatur tidak boleh punya dua item dengan jenis dan nomor
 * urut yang sama.
 *
 * Sebelum ini, setiap kali donasi rutin disimpan, sistem menyalin ulang SELURUH item
 * kumulatif alih-alih hanya menambah yang baru — sehingga item dan resinya berlipat
 * ganda (satu donatur pernah punya nomor urut 1 sebanyak tiga kali). Kode pembuatnya
 * sudah diperbaiki; kunci ini memastikan bug serupa tidak bisa lagi menyelinap lewat
 * jalur mana pun, sekarang maupun nanti.
 *
 * Kunci TIDAK dipasang bila masih ada baris lama yang bertabrakan — kalau dipaksa,
 * migrasi gagal dan aplikasi tidak bisa di-deploy. Baris lama itu sengaja tidak
 * dihapus otomatis: sebagian besar sudah terlanjur punya resi dan QR. Pembersihannya
 * menunggu keputusan pemilik data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->sudahAda()) {
            return;
        }

        $duplikat = $this->hitungDuplikat();

        if ($duplikat > 0) {
            $pesan = 'Kunci unik wakaf_items(donatur_id, wakaf_type, sequence_in_type) DILEWATI: '
                ."masih ada {$duplikat} kombinasi ganda pada data lama. "
                .'Perbaiki/hapus baris ganda itu lebih dulu, lalu jalankan ulang migrasi ini. '
                .'Selama kunci belum terpasang, tidak ada jaring pengaman di tingkat database.';

            Log::warning($pesan);

            return;
        }

        Schema::table('wakaf_items', function (Blueprint $table) {
            $table->unique(
                ['donatur_id', 'wakaf_type', 'sequence_in_type'],
                'wakaf_items_donatur_jenis_seq_unique'
            );
        });
    }

    public function down(): void
    {
        if (! $this->sudahAda()) {
            return;
        }

        Schema::table('wakaf_items', function (Blueprint $table) {
            $table->dropUnique('wakaf_items_donatur_jenis_seq_unique');
        });
    }

    private function sudahAda(): bool
    {
        foreach (Schema::getIndexes('wakaf_items') as $index) {
            if (($index['name'] ?? null) === 'wakaf_items_donatur_jenis_seq_unique') {
                return true;
            }
        }

        return false;
    }

    private function hitungDuplikat(): int
    {
        return DB::table('wakaf_items')
            ->select('donatur_id', 'wakaf_type', 'sequence_in_type')
            ->whereNotNull('donatur_id')
            ->groupBy('donatur_id', 'wakaf_type', 'sequence_in_type')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
    }
};
