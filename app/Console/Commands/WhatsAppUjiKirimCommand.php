<?php

namespace App\Console\Commands;

use App\Models\WhatsAppSetting;
use App\Services\WhatsApp\StarSenderClient;
use Illuminate\Console\Command;

/**
 * Menguji sambungan StarSender tanpa menyentuh data produksi sama sekali.
 *
 * Ini gerbangnya: kalau satu pesan uji tidak benar-benar sampai ke nomor yang
 * dituju, sisa fiturnya tidak ada gunanya. Sengaja TIDAK menulis ke tabel
 * antrean — yang diuji adalah sambungannya, bukan alurnya, dan tidak ada gunanya
 * meninggalkan riwayat palsu.
 */
class WhatsAppUjiKirimCommand extends Command
{
    protected $signature = 'whatsapp:uji-kirim
                          {nomor : Nomor tujuan, boleh berawalan 0 atau 62}
                          {--pesan= : Isi pesan (opsional)}';

    protected $description = 'Kirim satu pesan uji lewat StarSender untuk memastikan sambungannya hidup';

    public function handle(): int
    {
        $setting = WhatsAppSetting::active();

        if (! $setting) {
            $this->error('Pengaturan WhatsApp belum ada. Isi dulu di menu Notifikasi WhatsApp.');

            return self::FAILURE;
        }

        if (blank($setting->api_key)) {
            $this->error('Kunci API StarSender belum diisi.');

            return self::FAILURE;
        }

        $nomor = (string) $this->argument('nomor');
        $klien = StarSenderClient::dari($setting);

        $this->line("Memeriksa nomor {$nomor} ...");
        $cek = $klien->cekNomor($nomor);

        if ($cek['sukses'] && ($cek['mentah']['data']['status'] ?? false) !== true) {
            $this->warn('Nomor ini TIDAK terdaftar WhatsApp menurut StarSender.');
            $this->line('Balasan: '.json_encode($cek['mentah'], JSON_UNESCAPED_SLASHES));
            $this->info('Pesan uji tidak dikirim — mengirim ke nomor tak terdaftar hanya membebani kuota.');

            return self::FAILURE;
        }

        if (! $cek['sukses']) {
            $this->warn('Pemeriksaan nomor tidak bisa diselesaikan ('.$cek['pesan'].').');
            $this->line('Lanjut mencoba kirim, karena pemeriksaan bisa gagal sendiri tanpa berarti nomornya salah.');
        } else {
            $this->info('Nomor terdaftar WhatsApp.');
        }

        $pesan = (string) ($this->option('pesan') ?: 'Uji sambungan notifikasi Ekspedisi Quran. Kalau pesan ini sampai, sambungannya sudah benar.');

        $this->line('Mengirim pesan uji ...');
        $hasil = $klien->kirimTeks($nomor, $pesan);

        $this->newLine();
        $this->line('HTTP: '.($hasil['kode_http'] ?? '-'));
        $this->line('Balasan StarSender: '.json_encode($hasil['mentah'], JSON_UNESCAPED_SLASHES));

        if (! $hasil['sukses']) {
            $this->error('GAGAL: '.$hasil['pesan']);

            return self::FAILURE;
        }

        $this->info('BERHASIL: '.$hasil['pesan']);
        $this->line('Periksa WhatsApp di nomor tujuan untuk memastikan pesannya benar-benar sampai.');

        return self::SUCCESS;
    }
}
