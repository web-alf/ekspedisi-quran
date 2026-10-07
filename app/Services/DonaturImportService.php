<?php

namespace App\Services;

use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Models\WakafItem;
use App\Support\KodeDonatur;
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
        return $this->createFromRows([
            'kode_donatur' => $data['kode_donatur'],
            'nama_donatur' => $data['nama_donatur'],
            'no_hp' => $data['no_hp'],
            'email_donatur' => $data['email_donatur'] ?? null,
            'alamat_donatur' => $data['alamat_donatur'] ?? null,
            'donation_date' => $data['donation_date'],
            'doa_untuk_semua' => $data['doa_untuk_semua'] ?? null,
        ], [
            ['wakaf_type' => 'A5', 'jumlah' => (int) ($data['jumlah_a5'] ?? 0)],
            ['wakaf_type' => 'A6', 'jumlah' => (int) ($data['jumlah_a6'] ?? 0)],
            ['wakaf_type' => 'IQRA', 'jumlah' => (int) ($data['jumlah_iqra'] ?? 0)],
        ]);
    }

    /**
     * Impor SATU donatur dari beberapa baris sekaligus.
     *
     * Satu donatur boleh ditulis dalam beberapa baris ketika mushaf-mushafnya berbeda
     * nama wakif, doa, atau hubungannya. Semua baris itu tetap SATU donasi — makanya
     * `donation_count` bertambah sekali, bukan sekali per baris.
     *
     * @param  array{kode_donatur:string,nama_donatur:string,no_hp:string,email_donatur:?string,alamat_donatur:?string,donation_date:?string,doa_untuk_semua:?string}  $ringkasan
     * @param  list<array{wakaf_type:string,jumlah:int,wakif_name?:?string,doa_request?:?string,relationship_to_donatur?:?string}>  $baris
     */
    public function createFromRows(array $ringkasan, array $baris): Donatur
    {
        return DB::transaction(function () use ($ringkasan, $baris) {
            $kode = KodeDonatur::bersihkan($ringkasan['kode_donatur']);
            $donatur = Donatur::where('kode_donatur', $kode)->first();

            $totalA5 = 0;
            $totalA6 = 0;
            $totalIqra = 0;
            foreach ($baris as $b) {
                match ($b['wakaf_type']) {
                    'A5' => $totalA5 += $b['jumlah'],
                    'A6' => $totalA6 += $b['jumlah'],
                    'IQRA' => $totalIqra += $b['jumlah'],
                    default => null,
                };
            }

            $jenisWakafDipilih = [];
            if ($totalA5 > 0) {
                $jenisWakafDipilih[] = 'A5';
            }
            if ($totalA6 > 0) {
                $jenisWakafDipilih[] = 'A6';
            }
            if ($totalIqra > 0) {
                $jenisWakafDipilih[] = 'IQRA';
            }

            // Ada nama wakif sendiri-sendiri? Berarti doa tiap mushaf tidak lagi seragam.
            $adaNamaSendiri = false;
            foreach ($baris as $b) {
                if (($b['wakif_name'] ?? '') !== '') {
                    $adaNamaSendiri = true;
                    break;
                }
            }

            $atribut = [
                'nama_donatur' => $ringkasan['nama_donatur'],
                'no_hp' => $ringkasan['no_hp'],
                'email_donatur' => ($ringkasan['email_donatur'] ?? null) ?: null,
                'alamat_donatur' => ($ringkasan['alamat_donatur'] ?? null) ?: null,
                'donation_date' => $ringkasan['donation_date'],
                'prayer_mode' => $adaNamaSendiri ? 'customize_individual' : 'semua_donatur',
                'doa_untuk_semua' => ($ringkasan['doa_untuk_semua'] ?? null) ?: null,
            ];

            if ($donatur) {
                $donatur->update($atribut + [
                    'total_a5_count' => $donatur->total_a5_count + $totalA5,
                    'total_a6_count' => $donatur->total_a6_count + $totalA6,
                    'total_iqra_count' => $donatur->total_iqra_count + $totalIqra,
                    // Seluruh baris dalam berkas ini tetap SATU donasi.
                    'donation_count' => $donatur->donation_count + 1,
                    'jenis_wakaf_dipilih' => array_values(array_unique(array_merge($donatur->jenis_wakaf_dipilih ?? [], $jenisWakafDipilih))),
                    'created_by' => auth()->id() ?? $donatur->created_by ?? User::query()->value('id'),
                ]);
                $donatur->refresh();
            } else {
                $donatur = Donatur::create($atribut + [
                    'kode_donatur' => $kode,
                    'total_a5_count' => $totalA5,
                    'total_a6_count' => $totalA6,
                    'total_iqra_count' => $totalIqra,
                    'donation_count' => 1,
                    'jenis_wakaf_dipilih' => $jenisWakafDipilih,
                    'created_by' => auth()->id() ?? User::query()->value('id'),
                ]);
            }

            // Penomoran melanjutkan yang sudah ada supaya donasi rutin tidak menimpa nomor lama.
            $global = (int) $donatur->wakafItems()->max('global_sequence') + 1;
            $lanjut = [
                'A5' => (int) $donatur->wakafItems()->where('wakaf_type', 'A5')->max('sequence_in_type'),
                'A6' => (int) $donatur->wakafItems()->where('wakaf_type', 'A6')->max('sequence_in_type'),
                'IQRA' => (int) $donatur->wakafItems()->where('wakaf_type', 'IQRA')->max('sequence_in_type'),
            ];

            $items = [];
            foreach ($baris as $b) {
                $jenis = $b['wakaf_type'];
                for ($i = 0; $i < $b['jumlah']; $i++) {
                    $lanjut[$jenis]++;

                    $items[] = [
                        'donatur_id' => $donatur->id,
                        'wakaf_type' => $jenis,
                        'sequence_in_type' => $lanjut[$jenis],
                        'global_sequence' => $global++,
                        // Kolomnya NOT NULL: kalau baris ini tidak menyebut nama wakif,
                        // pakai nama donatur seperti perilaku lama supaya tidak ada mushaf
                        // yang kehilangan nama.
                        'wakif_name' => ($b['wakif_name'] ?? '') !== '' ? $b['wakif_name'] : $donatur->nama_donatur,
                        'doa_request' => ($b['doa_request'] ?? '') !== ''
                            ? $b['doa_request']
                            : (($ringkasan['doa_untuk_semua'] ?? null) ?: ''),
                        'relationship_to_donatur' => ($b['relationship_to_donatur'] ?? '') !== ''
                            ? $b['relationship_to_donatur']
                            : 'Diri sendiri',
                        'status' => 'pending',
                        'created_by' => $donatur->created_by,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            return $this->simpanItemsDanPengiriman($donatur, $items);
        });
    }

    /**
     * Simpan item wakaf lalu buatkan satu pengiriman (resi) untuk masing-masing.
     *
     * @param  list<array<string,mixed>>  $items
     */
    private function simpanItemsDanPengiriman(Donatur $donatur, array $items): Donatur
    {
        $newWakafItems = [];
        foreach ($items as $itemData) {
            $newWakafItems[] = WakafItem::create($itemData);
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
    }
}
