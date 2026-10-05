<?php

namespace App\Console\Commands;

use App\Models\Pengiriman;
use App\Services\QrCodeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Buatkan QR untuk resi yang belum punya.
 *
 * Latar: sistem ini punya 26 ribu resi tetapi hanya segelintir yang ber-QR,
 * sehingga halaman packing tidak bisa menampilkan barang untuk dipindai —
 * syaratnya `qr_code_path` terisi. Tanpa QR, alur packing tidak akan pernah
 * mulai walau status resinya sudah benar.
 *
 * Aman dijalankan berulang: resi yang QR-nya sudah ada DAN berkasnya masih ada
 * akan dilewati. Pakai --dry-run dulu untuk melihat rencananya.
 *
 * Jalankan bertahap (mis. --limit=500) supaya bisa dipantau; perkiraan
 * kecepatannya sekitar 4 ms per QR, jadi seluruh 26 ribu resi sekitar 2 menit.
 */
class GenerateQrCodesCommand extends Command
{
    protected $signature = 'qr:generate-missing
                            {--dry-run : Tampilkan rencananya saja, jangan buat berkas apa pun}
                            {--limit=0 : Batasi jumlah resi (0 = semua)}
                            {--jenis=0 : Batasi ke satu jenis Quran (0 = semua)}
                            {--buat-ulang : Buat ulang walau QR-nya sudah ada}';

    protected $description = 'Buatkan QR untuk resi yang belum punya (dan rapikan yang berkasnya hilang)';

    public function handle(QrCodeService $qr): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $buatUlang = (bool) $this->option('buat-ulang');
        $limit = (int) $this->option('limit');
        $jenis = (int) $this->option('jenis');

        $query = Pengiriman::query()->orderBy('id');

        if ($jenis > 0) {
            $query->where('jenis_quran_id', $jenis);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $kandidat = $query->get(['id', 'no_resi', 'qr_code_path', 'jenis_quran_id']);

        if (! $buatUlang) {
            $kandidat = $kandidat->reject(fn (Pengiriman $p) => $qr->sudahAda($p))->values();
        }

        $total = Pengiriman::count();

        // Kolom bisa terisi sementara berkasnya hilang. Karena itu dilaporkan
        // terpisah — kalau digabung, "sudah punya QR" akan terlihat beres
        // padahal berkasnya tidak ada dan resi itu tetap perlu dibuatkan.
        $kolomTerisi = Pengiriman::whereNotNull('qr_code_path')->where('qr_code_path', '!=', '')->count();
        $lengkap = $total - $kandidat->count();

        $this->info("Total resi: {$total}");
        $this->info("Kolom QR terisi: {$kolomTerisi} | benar-benar lengkap (berkas ada): {$lengkap}");
        $this->info("Akan dibuatkan QR: {$kandidat->count()}".($dryRun ? ' (mode uji — tidak ada berkas dibuat)' : ''));

        if ($kandidat->isEmpty()) {
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->newLine();
            $this->line('Contoh 5 resi pertama:');
            foreach ($kandidat->take(5) as $p) {
                $this->line("  {$p->no_resi} (id {$p->id}, jenis {$p->jenis_quran_id})");
            }

            $this->newLine();
            $this->line('Isi QR yang akan ditulis: nomor resi saja, format SVG, ukuran 300.');
            $this->line('Perkiraan: ~4 ms/QR, rata-rata ~1,7 KB/berkas.');

            return self::SUCCESS;
        }

        $berhasil = 0;
        $gagal = 0;
        $ukuranByte = 0;
        $galat = [];

        $this->withProgressBar($kandidat, function (Pengiriman $p) use ($qr, &$berhasil, &$gagal, &$ukuranByte, &$galat) {
            try {
                $path = $qr->buatDanSimpan($p);
                $ukuranByte += Storage::size($path);
                $berhasil++;

            } catch (\Throwable $e) {
                // Satu resi gagal tidak boleh menghentikan sisanya — 26 ribu
                // baris terlalu panjang untuk dibatalkan oleh satu galat.
                $gagal++;
                $galat[$p->no_resi] = $e->getMessage();
            }
        });

        $this->newLine(2);
        $this->info(sprintf(
            'Selesai. Berhasil: %d, gagal: %d, total berkas: %.1f MB',
            $berhasil,
            $gagal,
            $ukuranByte / 1024 / 1024
        ));

        if ($gagal > 0) {
            $this->warn("{$gagal} resi gagal dibuatkan QR:");
            foreach (array_slice($galat, 0, 10, true) as $resi => $pesan) {
                $this->line("  {$resi}: {$pesan}");
            }
            if ($gagal > 10) {
                $this->line('  ... dan '.($gagal - 10).' lainnya.');
            }
        }

        $sisa = Pengiriman::where(fn ($q) => $q->whereNull('qr_code_path')->orWhere('qr_code_path', ''))->count();
        $this->info("Sisa resi tanpa QR: {$sisa}");

        return $gagal > 0 ? self::FAILURE : self::SUCCESS;
    }
}
