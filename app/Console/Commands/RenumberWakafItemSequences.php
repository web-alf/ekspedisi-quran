<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Merapikan nomor urut item wakaf yang kembar pada donatur yang sama.
 *
 * Kembar ini muncul karena donatur yang berdonasi berulang pernah ditambahi item BARU
 * di samping item lama yang ditulis ulang, sehingga dua baris memakai nomor urut yang
 * sama. Akibatnya nomor "#N" tampil ganda dan kunci unik
 * (donatur_id, wakaf_type, sequence_in_type) tidak bisa dipasang.
 *
 * Command ini HANYA menyusun ulang nomornya: tidak ada satu baris pun yang dihapus dan
 * tidak ada resi yang disentuh. Urutan baris mengikuti id (urut waktu dibuat), jadi
 * riwayat donasi tetap sesuai urutan kejadiannya.
 */
class RenumberWakafItemSequences extends Command
{
    /**
     * @var string
     */
    protected $signature = 'wakaf-items:renumber-sequences
                            {--dry-run : Tampilkan rencana tanpa mengubah data}
                            {--donatur= : Batasi ke satu id donatur saja}';

    /**
     * @var string
     */
    protected $description = 'Susun ulang nomor urut item wakaf yang kembar agar tampil rapi dan kunci unik bisa dipasang';

    /**
     * Urutan jenis yang dipakai saat menomori, supaya hasilnya selalu sama.
     *
     * @var array<int, string>
     */
    private const URUTAN_JENIS = ['A5', 'A6', 'IQRA'];

    /**
     * Selisih sementara. Nilai baru ditulis dalam dua tahap supaya tidak pernah
     * bertabrakan, termasuk bila kunci unik sudah terpasang saat command ini dijalankan.
     */
    private const SELISIH_SEMENTARA = 1_000_000;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $hanyaDonatur = $this->option('donatur');

        $this->info('Merapikan nomor urut item wakaf...');
        $this->line('Mode uji-coba: '.($dryRun ? 'AKTIF — tidak ada data yang ditulis' : 'NONAKTIF — data akan diubah'));
        $this->newLine();

        $kembarSeq = $this->cariKembarSequence($hanyaDonatur);
        $kembarGlobal = $this->cariKembarGlobal($hanyaDonatur);

        // Dua jenis kembar, dan keduanya harus ditangani:
        //  - sequence_in_type kembar pada jenis yang sama  -> menghalangi kunci unik
        //  - global_sequence kembar                        -> nomor "#N" tampil ganda
        // Kembar global bisa muncul SENDIRI, tanpa kembar sequence: item A5#1 dan A6#1
        // sama-sama memakai global 1. Kalau hanya yang pertama diperiksa, kasus itu lolos.
        $idDonatur = $kembarSeq->pluck('donatur_id')
            ->merge($kembarGlobal->pluck('donatur_id'))
            ->unique()
            ->values();

        if ($idDonatur->isEmpty()) {
            $this->info('Tidak ada nomor urut yang kembar. Tidak ada yang perlu dikerjakan.');

            return self::SUCCESS;
        }

        $this->line('Nomor kembar pada jenis yang sama (menghalangi kunci unik) : '.$kembarSeq->count());
        $this->line('Nomor "#N" yang tampil ganda (global_sequence)              : '.$kembarGlobal->count());
        $this->line('Donatur terdampak                                          : '.$idDonatur->count());
        $this->newLine();

        $rencana = [];
        foreach ($idDonatur as $id) {
            $rencana[$id] = $this->susunRencana((int) $id);
        }

        $totalDiubah = collect($rencana)->sum(fn ($r) => count($r['perubahan']));
        $this->line('Baris yang nomornya akan disesuaikan             : '.$totalDiubah);
        $this->newLine();

        if ($totalDiubah === 0) {
            $this->info('Tidak ada nomor yang perlu disesuaikan.');

            return self::SUCCESS;
        }

        // 12 contoh, supaya bisa diperiksa sebelum data disentuh.
        $this->line('12 contoh perubahan:');
        $this->table(
            ['id', 'donatur', 'jenis', 'nomor lama', 'nomor baru', 'global lama', 'global baru'],
            collect($rencana)
                ->flatMap(fn ($r) => $r['perubahan'])
                ->take(12)
                ->map(fn ($p) => [
                    $p['id'], $p['kode'], $p['wakaf_type'], $p['lama'], $p['baru'], $p['global_lama'], $p['global_baru'],
                ])
                ->all()
        );

        if ($dryRun) {
            $this->newLine();
            $this->info('Uji-coba selesai. Tidak ada data yang diubah.');
            $this->line('Jalankan tanpa --dry-run untuk menerapkan.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Menerapkan...');

        DB::transaction(function () use ($rencana) {
            foreach ($rencana as $id => $r) {
                // Tahap 1: geser semua baris donatur ini ke rentang sementara agar tidak
                // ada nomor yang bertabrakan di tengah proses, walau kunci unik sudah ada.
                $idBaris = collect($r['perubahan'])->pluck('id')->all();

                foreach ($idBaris as $idItem) {
                    DB::table('wakaf_items')->where('id', $idItem)->update([
                        'sequence_in_type' => self::SELISIH_SEMENTARA + $idItem,
                    ]);
                }

                // Tahap 2: tulis nomor akhirnya.
                foreach ($r['perubahan'] as $p) {
                    DB::table('wakaf_items')->where('id', $p['id'])->update([
                        'sequence_in_type' => $p['baru'],
                        'global_sequence' => $p['global_baru'],
                    ]);
                }
            }
        });

        $this->info("Selesai. {$totalDiubah} baris disesuaikan, 0 baris dihapus.");

        // Periksa ulang KEDUA jenis kembar: kalau masih ada, jangan mengaku beres.
        $sisaSeq = $this->cariKembarSequence($hanyaDonatur)->count();
        $sisaGlobal = $this->cariKembarGlobal($hanyaDonatur)->count();
        $this->line('Sisa nomor kembar pada jenis yang sama : '.$sisaSeq);
        $this->line('Sisa nomor "#N" yang tampil ganda      : '.$sisaGlobal);

        return ($sisaSeq === 0 && $sisaGlobal === 0) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Nomor kembar PADA JENIS YANG SAMA — inilah yang menghalangi kunci unik.
     *
     * @return Collection<int, object>
     */
    private function cariKembarSequence(?string $hanyaDonatur)
    {
        return DB::table('wakaf_items')
            ->select('donatur_id', 'wakaf_type', 'sequence_in_type', DB::raw('COUNT(*) as n'))
            ->when($hanyaDonatur !== null, fn ($q) => $q->where('donatur_id', (int) $hanyaDonatur))
            ->groupBy('donatur_id', 'wakaf_type', 'sequence_in_type')
            ->havingRaw('COUNT(*) > 1')
            ->get();
    }

    /**
     * Nomor "#N" yang tampil ganda — global_sequence kembar pada donatur yang sama.
     *
     * Bisa muncul SENDIRI tanpa kembar sequence: item A5#1 dan A6#1 sama-sama memakai
     * global 1, sehingga donatur itu menampilkan dua item bernomor "#1" — padahal
     * sequence_in_type-nya sah dan tidak menghalangi kunci unik apa pun.
     *
     * @return Collection<int, object>
     */
    private function cariKembarGlobal(?string $hanyaDonatur)
    {
        return DB::table('wakaf_items')
            ->select('donatur_id', 'global_sequence', DB::raw('COUNT(*) as n'))
            ->when($hanyaDonatur !== null, fn ($q) => $q->where('donatur_id', (int) $hanyaDonatur))
            ->groupBy('donatur_id', 'global_sequence')
            ->havingRaw('COUNT(*) > 1')
            ->get();
    }

    /**
     * Menyusun rencana penomoran ulang untuk satu donatur.
     *
     * Hanya baris yang nomornya benar-benar berubah yang dilaporkan, supaya yang
     * dikerjakan minimum dan mudah diperiksa.
     *
     * @return array{perubahan: array<int, array<string, mixed>>}
     */
    private function susunRencana(int $idDonatur): array
    {
        $kode = (string) DB::table('donatur')->where('id', $idDonatur)->value('kode_donatur');

        $jenisAda = DB::table('wakaf_items')->where('donatur_id', $idDonatur)
            ->distinct()->pluck('wakaf_type')->all();

        // Jenis yang dikenal didahulukan dengan urutan tetap; jenis lain menyusul agar
        // baris apa pun tetap mendapat nomor.
        $urutan = array_values(array_filter(self::URUTAN_JENIS, fn ($j) => in_array($j, $jenisAda, true)));
        foreach ($jenisAda as $j) {
            if (! in_array($j, $urutan, true)) {
                $urutan[] = $j;
            }
        }

        $perubahan = [];
        $globalBerikutnya = 1;

        foreach ($urutan as $jenis) {
            $baris = DB::table('wakaf_items')
                ->where('donatur_id', $idDonatur)
                ->where('wakaf_type', $jenis)
                ->orderBy('id')
                ->get(['id', 'sequence_in_type', 'global_sequence']);

            $nomor = 1;
            foreach ($baris as $b) {
                $globalBaru = $globalBerikutnya++;

                if ((int) $b->sequence_in_type !== $nomor || (int) $b->global_sequence !== $globalBaru) {
                    $perubahan[] = [
                        'id' => $b->id,
                        'kode' => $kode,
                        'wakaf_type' => $jenis,
                        'lama' => (int) $b->sequence_in_type,
                        'baru' => $nomor,
                        'global_lama' => (int) $b->global_sequence,
                        'global_baru' => $globalBaru,
                    ];
                }

                $nomor++;
            }
        }

        return ['perubahan' => $perubahan];
    }
}
