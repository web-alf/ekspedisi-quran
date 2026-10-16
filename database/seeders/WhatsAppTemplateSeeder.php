<?php

namespace Database\Seeders;

use App\Models\WhatsAppTemplate;
use Illuminate\Database\Seeder;

/**
 * Template bawaan notifikasi WhatsApp.
 *
 * Satu baris = satu peristiwa yang bisa dinyalakan/dimatikan dari halaman
 * pengaturan tanpa mengubah kode. Karena itu "peristiwa mana yang dikirim"
 * adalah keputusan DATA: matikan saja baris yang tidak diinginkan.
 *
 * `name` adalah kunci yang dirujuk kode — JANGAN diubah setelah dipakai, atau
 * notifikasi akan diam-diam berhenti. Yang boleh diganti kapan saja adalah
 * `title` dan `content`.
 *
 * Idempoten: baris yang sudah ada tidak ditimpa, supaya teks yang sudah
 * disunting pengguna tidak tertimpa saat seeder dijalankan ulang.
 */
class WhatsAppTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $template) {
            WhatsAppTemplate::firstOrCreate(
                ['name' => $template['name']],
                $template
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            [
                'name' => 'sertifikat_siap',
                'category' => 'sertifikat',
                'title' => 'Sertifikat wakaf siap diunduh',
                'variables' => ['nama', 'batch', 'link'],
                'content' => "Assalamu'alaikum {nama},\n\n"
                    ."Alhamdulillah, wakaf Al-Qur'an Anda pada batch {batch} telah selesai disalurkan. "
                    ."Sertifikatnya sudah siap diunduh di tautan berikut:\n{link}\n\n"
                    .'Semoga menjadi amal jariyah yang pahalanya terus mengalir. Jazakumullahu khairan.',
                'description' => 'Dikirim ke donatur setelah seluruh resi batchnya diterima dan sertifikatnya dibuat.',
            ],
            [
                'name' => 'resi_diterima',
                'category' => 'pengiriman',
                'title' => 'Kiriman wakaf sudah diterima',
                'variables' => ['nama', 'resi'],
                'content' => "Assalamu'alaikum {nama},\n\n"
                    ."Kabar baik: kiriman Al-Qur'an wakaf Anda dengan nomor resi {resi} sudah kami terima di tujuan. "
                    ."Terima kasih atas kepercayaannya.\n\n"
                    .'Semoga bermanfaat bagi para penerima.',
                'description' => 'Dikirim ke donatur saat status resinya berubah menjadi diterima.',
            ],
            [
                'name' => 'permintaan_disetujui',
                'category' => 'permintaan',
                'title' => 'Permintaan mushaf disetujui',
                'variables' => ['nama', 'lembaga', 'resi'],
                'content' => "Assalamu'alaikum {nama},\n\n"
                    ."Permintaan mushaf Al-Qur'an dari {lembaga} telah kami setujui dan sedang disiapkan pengirimannya.\n"
                    ."Nomor resi: {resi}\n\n"
                    .'Terima kasih.',
                'description' => 'Dikirim ke pengurus lembaga saat permintaan mushafnya disetujui.',
            ],
        ];
    }
}
