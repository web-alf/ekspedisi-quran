<?php

namespace App\Services;

use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Models\WakafItem;
use Illuminate\Support\Facades\DB;

class DonaturImportService
{
    /**
     * Create or update a donatur from import data and generate related records.
     *
     *
     * @throws \Exception
     */
    public function createFromArray(array $data): Donatur
    {
        return DB::transaction(function () use ($data) {
            $donatur = Donatur::where('kode_donatur', $data['kode_donatur'])->first();

            $currentA5 = (int) ($data['jumlah_a5'] ?? 0);
            $currentA6 = (int) ($data['jumlah_a6'] ?? 0);
            $currentIqra = (int) ($data['jumlah_iqra'] ?? 0);

            $jenisWakafDipilih = [];
            if ($currentA5 > 0) {
                $jenisWakafDipilih[] = 'A5';
            }
            if ($currentA6 > 0) {
                $jenisWakafDipilih[] = 'A6';
            }
            if ($currentIqra > 0) {
                $jenisWakafDipilih[] = 'IQRA';
            }

            if ($donatur) {
                $donatur->update([
                    'nama_donatur' => $data['nama_donatur'],
                    'no_hp' => $data['no_hp'],
                    'email_donatur' => $data['email_donatur'] ?? null,
                    'alamat_donatur' => $data['alamat_donatur'] ?? null,
                    'donation_date' => $data['donation_date'],
                    'total_a5_count' => $donatur->total_a5_count + $currentA5,
                    'total_a6_count' => $donatur->total_a6_count + $currentA6,
                    'total_iqra_count' => $donatur->total_iqra_count + $currentIqra,
                    'donation_count' => $donatur->donation_count + 1,
                    'jenis_wakaf_dipilih' => array_unique(array_merge($donatur->jenis_wakaf_dipilih ?? [], $jenisWakafDipilih)),
                    'prayer_mode' => 'semua_donatur',
                    'doa_untuk_semua' => $data['doa_untuk_semua'] ?? null,
                    'created_by' => auth()->id()
                        ?? $donatur->created_by
                        ?? User::query()->value('id'),
                ]);
                $donatur->refresh();
            } else {
                $donatur = Donatur::create([
                    'kode_donatur' => $data['kode_donatur'],
                    'nama_donatur' => $data['nama_donatur'],
                    'no_hp' => $data['no_hp'],
                    'email_donatur' => $data['email_donatur'] ?? null,
                    'alamat_donatur' => $data['alamat_donatur'] ?? null,
                    'donation_date' => $data['donation_date'],
                    'total_a5_count' => $currentA5,
                    'total_a6_count' => $currentA6,
                    'total_iqra_count' => $currentIqra,
                    'donation_count' => 1,
                    'jenis_wakaf_dipilih' => $jenisWakafDipilih,
                    'prayer_mode' => 'semua_donatur',
                    'doa_untuk_semua' => $data['doa_untuk_semua'] ?? null,
                    'created_by' => auth()->id() ?? User::query()->value('id'),
                ]);
            }

            // Generate wakaf items HANYA sejumlah delta baris ini (bukan total kumulatif),
            // supaya import ulang donatur yang sama tidak menggandakan item & pengiriman.
            $wakafItemsData = $donatur->generateWakafItems([
                'a5' => $currentA5,
                'a6' => $currentA6,
                'iqra' => $currentIqra,
            ]);
            $newWakafItems = [];
            foreach ($wakafItemsData as $itemData) {
                $wakafItem = WakafItem::create($itemData);
                $newWakafItems[] = $wakafItem;
            }

            // Load jenis quran mapping from cache
            $jenisQuranMap = cache()->remember('jenis_quran_mapping', 3600, function () {
                $jenisQuranMap = [];
                $jenisQurans = JenisQuran::select('id', 'nama_jenis')->get();

                if ($jenisQurans->isEmpty()) {
                    throw new \Exception('Tidak ada data jenis Quran yang tersedia.');
                }

                foreach ($jenisQurans as $jq) {
                    $nama = strtoupper($jq->nama_jenis ?? '');
                    if (str_contains($nama, 'A5')) {
                        $jenisQuranMap['A5'] = $jq->id;
                    } elseif (str_contains($nama, 'A6')) {
                        $jenisQuranMap['A6'] = $jq->id;
                    } elseif (str_contains($nama, 'IQRO') || str_contains($nama, 'IQRA')) {
                        $jenisQuranMap['IQRA'] = $jq->id;
                    }
                }

                $requiredTypes = ['A5', 'A6', 'IQRA'];
                $missingTypes = array_diff($requiredTypes, array_keys($jenisQuranMap));
                if (! empty($missingTypes)) {
                    throw new \Exception('Konfigurasi jenis Quran tidak lengkap untuk: '.implode(', ', $missingTypes));
                }

                return $jenisQuranMap;
            });

            $defaultStatusId = StatusPengiriman::getDefaultStatusId();
            if (! $defaultStatusId) {
                throw new \Exception('Status pengiriman default tidak ditemukan.');
            }

            foreach ($newWakafItems as $wakafItem) {
                $jenisQuranId = $jenisQuranMap[$wakafItem->wakaf_type] ?? null;
                if (! $jenisQuranId) {
                    throw new \Exception("JenisQuran tidak ditemukan untuk tipe: {$wakafItem->wakaf_type}");
                }

                $pengiriman = Pengiriman::create([
                    'donatur_id' => $donatur->id,
                    'wakaf_item_id' => $wakafItem->id,
                    'jenis_quran_id' => $jenisQuranId,
                    'jumlah_quran' => 1,
                    'tanggal_wakaf' => $donatur->donation_date,
                    'status_id' => $defaultStatusId,
                    'nama_penerima' => null,
                    'no_hp_penerima' => null,
                    'alamat_tujuan' => null,
                    'catatan' => "Donatur: {$donatur->nama_donatur} | Doa: ".($wakafItem->doa_request ?? '-'),
                    'created_by' => auth()->id(),
                ]);

                $wakafItem->update(['pengiriman_id' => $pengiriman->id]);
            }

            return $donatur;
        });
    }
}
