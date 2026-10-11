<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Template sertifikat bawaan.
 *
 * Tanpa satu pun template, seluruh jalur sertifikat berhenti: PDF-nya mustahil
 * dibuat, dan setiap percobaan mencetak dulu meninggalkan catatan sertifikat hampa.
 *
 * Gambar desainnya sengaja disimpan di `database/seeders/assets/` supaya ikut
 * terbawa saat kode di-deploy: `storage/app/public` tidak ikut ter-deploy, jadi
 * berkas yang hanya ada di sana akan hilang begitu server dipasang ulang.
 *
 * Seperti WhatsAppTemplateSeeder, seeder ini TIDAK menimpa template yang sudah
 * ada — posisi field yang sudah disesuaikan lewat menu Template Sertifikat tidak
 * boleh direset.
 */
class CertificateTemplateSeeder extends Seeder
{
    /**
     * Posisi tiap field diukur dari pita kosong desainnya (kanvas 2000 x 1414):
     * nama wakif di celah sesudah kalimat pembuka, tanggal hijriah di celah
     * antara paragraf penutup dan tanda tangan.
     *
     * @var array<int, array<string, mixed>>
     */
    private const TEMPLATE = [
        [
            'berkas' => 'sertifikat-wakaf-ornamen-emas.png',
            'name' => 'Sertifikat Wakaf - Ornamen Emas',
            'description' => 'Berornamen floral emas dengan judul ungu. Nama wakif di celah sesudah kalimat pembuka, tanggal hijriah di atas tanda tangan.',
            'is_default' => true,
            'field_positions' => [
                'wakif_name' => ['x' => 1000, 'y' => 615, 'font_size' => 40, 'color' => '#a53692'],
                'hijri_date' => ['x' => 1000, 'y' => 1020, 'font_size' => 20, 'color' => '#000000'],
            ],
        ],
        [
            'berkas' => 'sertifikat-wakaf-ungu-emas.png',
            'name' => 'Sertifikat Wakaf - Ungu Emas',
            'description' => 'Judul emas dengan sudut ungu. Nama wakif di celah sesudah kalimat pembuka, tanggal hijriah di atas tanda tangan.',
            'is_default' => false,
            'field_positions' => [
                'wakif_name' => ['x' => 1000, 'y' => 692, 'font_size' => 40, 'color' => '#a53692'],
                'hijri_date' => ['x' => 1000, 'y' => 1006, 'font_size' => 20, 'color' => '#000000'],
            ],
        ],
    ];

    public function run(): void
    {
        $pembuat = User::query()->orderBy('id')->value('id');

        if (! $pembuat) {
            $this->command?->warn('Template sertifikat bawaan dilewati: belum ada pengguna sama sekali.');

            return;
        }

        foreach (self::TEMPLATE as $data) {
            $slug = Str::slug($data['name']);

            if (CertificateTemplate::where('slug', $slug)->exists()) {
                continue;
            }

            $tujuan = 'certificate-templates/'.$data['berkas'];

            if (! Storage::disk('public')->exists($tujuan)) {
                Storage::disk('public')->put(
                    $tujuan,
                    File::get(database_path('seeders/assets/certificate-templates/'.$data['berkas']))
                );
            }

            CertificateTemplate::create([
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'],
                'template_file_path' => $tujuan,
                'field_positions' => $data['field_positions'],
                'width' => 2000,
                'height' => 1414,
                'is_active' => true,
                // Jangan rebut posisi bawaan bila admin sudah menentukan sendiri.
                'is_default' => $data['is_default'] && ! CertificateTemplate::where('is_default', true)->exists(),
                'created_by' => $pembuat,
            ]);
        }
    }
}
