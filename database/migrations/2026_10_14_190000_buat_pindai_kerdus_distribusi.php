<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak pindai kerdus oleh sisi DISTRIBUSI (manager / distribusi).
 *
 * Yang dicatat: kerdus apa yang dipindai, SIAPA yang memindai, JAM berapa, dan
 * berapa eks mushaf di dalamnya (jenisnya A5/A6/Iqra — satu kerdus selalu satu
 * jenis). Kerdus bisa dipindai lewat kamera (QR) maupun diketik manual.
 *
 * Kenapa tabel tersendiri, bukan menumpang tabel yang sudah ada:
 *   - `packing_items` mencatat PEMASUKAN mushaf ke kerdus di gudang (oleh petugas
 *     gudang). Yang ini mencatat PERPINDAHAN kerdus di sisi distribusi — pelakunya
 *     orang yang berbeda, dan satu kerdus bisa dipindai berkali-kali.
 *   - `packing_scan_logs` sengaja hanya menyimpan scan yang GAGAL, karena scan
 *     sukses sudah punya jejak di `packing_items`. Pindai kerdus di sisi distribusi
 *     tidak menghasilkan `packing_items` apa pun, jadi tidak punya jejak lain.
 *
 * Sifatnya CATATAN, bukan penggerak alur: tidak ada status pengiriman yang berubah
 * karena pindai di sini. Satu pindai = satu baris jejak.
 *
 * Izinnya dipisah ke migrasi berikutnya (…190100_beri_izin_pindai_kerdus): skema dan
 * wewenang punya jalur pemulihannya sendiri-sendiri, dan migrasi izin itu harus bisa
 * dijalankan ulang tanpa menyentuh tabel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pindai_kerdus_logs', function (Blueprint $table) {
            $table->id();

            // Siapa yang memindai. Nullable + nullOnDelete: jejak serah terima adalah
            // catatan pertanggungjawaban, jadi ia TIDAK ikut terhapus saat akunnya
            // dihapus.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Kerdus yang dipindai. Nullable: kerdus yang labelnya rusak atau belum
            // terdaftar di sistem tetap harus bisa dicatat (lihat kolom `asal`).
            $table->foreignId('packing_box_id')->nullable()->constrained('packing_boxes')->nullOnDelete();

            // Kode yang benar-benar dipindai/diketik, apa adanya. Disimpan terpisah dari
            // relasi kerdus supaya jejaknya tetap terbaca walau kerdusnya kelak dihapus.
            $table->string('kode_kerdus', 100);

            // Satu kerdus satu jenis, jadi jenis ikut baris ini. Nullable: kerdus yang
            // belum ditentukan jenisnya (baru dibuat, masih kosong) tetap bisa dipindai.
            $table->foreignId('jenis_quran_id')->nullable()->constrained('jenis_quran')->nullOnDelete();

            // POTRET isi kerdus saat dipindai, bukan hitungan ulang dari isi kerdus
            // sekarang: isi kerdus bisa berubah setelah pindai (diisi lagi di gudang),
            // dan laporan "berapa eks yang saya pindai kemarin" harus tetap benar.
            $table->unsignedInteger('jumlah_mushaf')->default(0);

            // 'qr'     — dibaca kamera
            // 'manual' — diketik petugas (label rusak / kamera tidak bisa dipakai)
            $table->string('metode', 10)->default('qr');

            // 'kerdus'  — kerdus terdaftar, jenis & jumlah diambil dari sistem
            // 'mandiri' — kerdus belum terdaftar; jenis & jumlah diisi petugas
            $table->string('asal', 20)->default('kerdus');

            // Kerdus ini sudah pernah dipindai sebelumnya (oleh siapa pun). Pindai ulang
            // tetap dicatat sebagai jejak, tetapi dihitung SEKALI di ringkasan supaya
            // jumlah eks tidak menggelembung karena dua kali pindai.
            $table->boolean('ulang')->default(false);

            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->index(['kode_kerdus', 'created_at']);
            $table->index(['jenis_quran_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pindai_kerdus_logs');
    }
};
