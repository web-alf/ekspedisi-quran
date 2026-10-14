<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Jaring pengaman: satu batch wakaf hanya punya satu sertifikat, dan satu
 * donatur hanya punya satu sertifikat konsolidasi.
 *
 * Sebelum ini pembuatnya "cek dulu, lalu buat" tanpa kunci, sehingga dua
 * proses bersamaan (klik ganda, jalur sinkron + job, scheduler + admin)
 * sama-sama membuat sertifikat. Dengan kunci ini, `firstOrCreate` di Laravel
 * menangkap tabrakan dan mengembalikan sertifikat yang sudah ada.
 *
 * Sertifikat konsolidasi punya `wakaf_batch_id` NULL, jadi kuncinya memakai
 * kolom turunan `donatur_konsolidasi_id` (= donatur_id hanya bila
 * is_consolidated). NULL tidak bertabrakan di unique index MySQL.
 *
 * Seperti migrasi kunci wakaf_items, kunci TIDAK dipasang bila masih ada baris
 * lama yang ganda — baris itu menunggu keputusan pemilik data, bukan dihapus
 * otomatis.
 */
return new class extends Migration
{
    private const KUNCI_BATCH = 'sertifikat_wakaf_batch_id_unique';

    private const KUNCI_KONSOLIDASI = 'sertifikat_donatur_konsolidasi_unique';

    public function up(): void
    {
        if (! Schema::hasColumn('sertifikat', 'donatur_konsolidasi_id')) {
            Schema::table('sertifikat', function (Blueprint $table) {
                $table->unsignedBigInteger('donatur_konsolidasi_id')
                    ->nullable()
                    ->storedAs('IF(is_consolidated = 1, donatur_id, NULL)');
            });
        }

        $this->pasangBilaBersih(
            self::KUNCI_BATCH,
            'wakaf_batch_id',
            'sertifikat(wakaf_batch_id)'
        );

        $this->pasangBilaBersih(
            self::KUNCI_KONSOLIDASI,
            'donatur_konsolidasi_id',
            'sertifikat konsolidasi per donatur'
        );
    }

    public function down(): void
    {
        foreach ([self::KUNCI_BATCH, self::KUNCI_KONSOLIDASI] as $nama) {
            if ($this->sudahAda($nama)) {
                Schema::table('sertifikat', fn (Blueprint $table) => $table->dropUnique($nama));
            }
        }

        if (Schema::hasColumn('sertifikat', 'donatur_konsolidasi_id')) {
            Schema::table('sertifikat', fn (Blueprint $table) => $table->dropColumn('donatur_konsolidasi_id'));
        }
    }

    private function pasangBilaBersih(string $nama, string $kolom, string $label): void
    {
        if ($this->sudahAda($nama)) {
            return;
        }

        $duplikat = DB::table('sertifikat')
            ->select($kolom)
            ->whereNotNull($kolom)
            ->groupBy($kolom)
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($duplikat > 0) {
            Log::warning("Kunci unik {$label} DILEWATI: masih ada {$duplikat} nilai ganda pada data lama. "
                .'Bereskan sertifikat ganda itu lebih dulu, lalu jalankan ulang migrasi ini.');

            return;
        }

        Schema::table('sertifikat', fn (Blueprint $table) => $table->unique($kolom, $nama));
    }

    private function sudahAda(string $nama): bool
    {
        foreach (Schema::getIndexes('sertifikat') as $index) {
            if (($index['name'] ?? null) === $nama) {
                return true;
            }
        }

        return false;
    }
};
