<?php

namespace App\Console\Commands;

use App\Models\MushafRequest;
use App\Services\MushafAddressResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lengkapi kolom wilayah (provinsi..kelurahan) pada permintaan mushaf LAMA yang
 * diimpor sebelum pengisian otomatis ada.
 *
 * Aman dijalankan berulang: baris yang sudah punya provinsi dilewati kecuali
 * --force dipakai. Pakai --dry-run dulu untuk melihat rencananya.
 */
class BackfillMushafAddressCommand extends Command
{
    protected $signature = 'mushaf:backfill-address
                            {--dry-run : Tampilkan rencananya saja, jangan ubah apa pun}
                            {--force : Isi ulang walau provinsinya sudah terisi}
                            {--limit=0 : Batasi jumlah baris (0 = semua)}';

    protected $description = 'Lengkapi kolom wilayah permintaan mushaf hasil import lama';

    public function handle(MushafAddressResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');

        $query = MushafRequest::query()
            ->whereNotNull('alamat_lengkap')
            ->where('alamat_lengkap', '!=', '');

        if (! $force) {
            $query->where(fn ($q) => $q->whereNull('provinsi')->orWhere('provinsi', ''));
        }

        $query->orderBy('id');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $sasaran = $query->get();
        $this->info("Baris yang perlu dilengkapi: {$sasaran->count()}".($dryRun ? ' (mode uji, tidak ada yang diubah)' : ''));

        if ($sasaran->isEmpty()) {
            return self::SUCCESS;
        }

        $berhasil = 0;
        $gagal = 0;

        $this->withProgressBar($sasaran, function (MushafRequest $req) use ($resolver, $dryRun, &$berhasil, &$gagal) {
            // Pakai tautan peta yang tersimpan (kalau ada) — itulah sumber
            // koordinat, dan tanpanya wilayah tidak bisa ditentukan sama sekali.
            $hasIl = $resolver->resolve($req->alamat_lengkap, $req->link_gmaps);

            if (! $hasIl['provinsi']) {
                $gagal++;

                return;
            }

            if ($dryRun) {
                $berhasil++;

                return;
            }

            DB::transaction(function () use ($req, $hasIl, &$berhasil) {
                $req->update([
                    'provinsi' => $hasIl['provinsi'],
                    'provinsi_id' => $hasIl['provinsi_id'],
                    'kota_kabupaten' => $hasIl['kota_kabupaten'],
                    'kota_kabupaten_id' => $hasIl['kota_kabupaten_id'],
                    'kecamatan' => $hasIl['kecamatan'],
                    'kecamatan_id' => $hasIl['kecamatan_id'],
                    'kelurahan_desa' => $hasIl['kelurahan_desa'],
                    'kelurahan_desa_id' => $hasIl['kelurahan_desa_id'],
                    'kode_pos' => $hasIl['kode_pos'],
                    'alamat_detail' => $hasIl['alamat_detail'],
                ]);
                $berhasil++;
            });
        });

        $this->newLine(2);
        $this->info("Selesai. Terisi: {$berhasil}, tidak bisa ditentukan: {$gagal}");

        if ($gagal > 0) {
            $this->warn("{$gagal} baris tidak bisa dicocokkan — teks alamatnya tidak menyebut wilayah yang ada di sumber data, dan tanpa tautan peta koordinatnya tidak diketahui. Kolomnya DIBIARKAN KOSONG, bukan ditebak.");
        }

        return self::SUCCESS;
    }
}
