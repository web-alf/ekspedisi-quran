<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template import permintaan mushaf.
 *
 * Kolomnya SENGAJA disamakan dengan field yang ada di kartu "Informasi
 * Lembaga" pada halaman detail permintaan (/admin/mushaf-requests/{id}).
 * Sebelumnya template hanya memuat satu kolom teks `alamat_lengkap`, sedangkan
 * form itu memecah alamat menjadi provinsi / kota-kabupaten / kecamatan /
 * kelurahan / kode pos / detail — sehingga kolom-kolom itu selalu kosong pada
 * hasil import, dan "Informasi Lembaga" tidak bisa disimpan karena latitude dan
 * longitude-nya ikut kosong.
 *
 * Yang mengisi kolom wilayah di template tidak perlu mengisi kolom teks apa pun;
 * yang hanya punya satu baris alamat cukup mengisi `alamat_lengkap` dan kolom
 * wilayahnya dikosongkan (akan diuraikan otomatis bila ada tautan peta).
 */
class MushafRequestTemplateExport implements FromArray, WithColumnWidths, WithEvents, WithHeadings, WithStyles
{
    /**
     * Nama kolom template — satu sumber kebenaran dengan pengimpornya.
     *
     * @var array<int, string>
     */
    public const KOLOM = [
        'nama_lembaga',
        'nama_penanggung_jawab_1',
        'nomor_hp',
        'jabatan_penanggung_jawab_1',
        'kategori_lembaga',
        'provinsi',
        'kota_kabupaten',
        'kecamatan',
        'kelurahan_desa',
        'kode_pos',
        'alamat_detail',
        'alamat_lengkap',
        'latitude',
        'longitude',
        'link_gmaps',
        'jumlah_mushaf_a5',
        'jumlah_mushaf_a6',
        'jumlah_iqra',
        'urgensi',
        'urgensi_request',
        'sumber_info',
    ];

    /**
     * Pilihan kategori lembaga — harus sama persis dengan daftar pada kartu
     * "Informasi Lembaga" dan dengan nilai yang diterima halaman publik.
     *
     * @var array<int, string>
     */
    public const KATEGORI = [
        'Pondok Pesantren',
        "Rumah Tahfidz/Rumah Qur'an",
        'TPQ/TPA/Madin',
        'Sekolah/Madrasah',
        'Masjid/Mushola/Majelis Taklim/Jamaah Masjid',
        'Masyarakat/Jamaah Alfatihah',
        'Organisasi/Paguyuban/Event Sosial/Komunitas',
        'Santri & Karyawan Alfatihah',
        'Yayasan',
        'Panti Asuhan/Anak Yatim',
        'RT/RW/Pemerintah Desa/Kecamatan',
        'Lembaga Lainnya',
        'Muallaf',
        'Penerima Manfaat Khusus Lainnya',
    ];

    /**
     * Baris contoh.
     *
     * Sengaja memakai nama LEMBAGA FIKTIF. Tiga baris sebelumnya adalah data
     * pemohon sungguhan — nama lembaga, alamat, dan nomor HP-nya lengkap dan
     * masih aktif (REQ-2026-00025 s/d 00027), sehingga template yang diunduh
     * dan diedarkan berisi data pribadi orang lain.
     *
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return [
            // Contoh 1 — alamat dipecah per kolom, koordinat diisi sendiri.
            [
                'TPQ Contoh Al Falah',
                'Nama Penanggung Jawab',
                '081234567890',
                'Ketua',
                'TPQ/TPA/Madin',
                'Jawa Timur',
                'Kabupaten Blitar',
                'Garum',
                'Contoh Kelurahan',
                '66181',
                'Jl. Contoh No. 1, RT.2/RW.5, Lingkungan Contoh',
                '',
                -8.0868357,
                112.2396983,
                'https://maps.app.goo.gl/contohBarisSatu',
                100,
                0,
                0,
                'sedang',
                'Banyak Al-Qur\'an yang sudah rusak dan perlu diganti',
                'WhatsApp',
            ],
            // Contoh 2 — hanya mengisi satu kolom alamat + tautan peta; kolom
            // wilayah sengaja dikosongkan karena akan diuraikan otomatis.
            [
                'MI Contoh Nurul Huda',
                'Nama Pengurus Kedua',
                '081298765432',
                'Sekretaris',
                'Sekolah/Madrasah',
                '',
                '',
                '',
                '',
                '',
                '',
                'Jl. Contoh Raya No. 2, Kec. Garum, Blitar',
                '',
                '',
                'https://maps.app.goo.gl/contohBarisDua',
                0,
                50,
                0,
                'tinggi',
                'Untuk pembelajaran tahun ajaran baru',
                'Instagram',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return self::KOLOM;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 11,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4CAF50'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
            // Dua baris contoh; diberi warna supaya jelas harus dihapus.
            '2:3' => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8F5E9'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 28, // nama_lembaga
            'B' => 25, // nama_penanggung_jawab_1
            'C' => 16, // nomor_hp
            'D' => 20, // jabatan_penanggung_jawab_1
            'E' => 34, // kategori_lembaga
            'F' => 18, // provinsi
            'G' => 20, // kota_kabupaten
            'H' => 18, // kecamatan
            'I' => 20, // kelurahan_desa
            'J' => 10, // kode_pos
            'K' => 40, // alamat_detail
            'L' => 40, // alamat_lengkap
            'M' => 14, // latitude
            'N' => 14, // longitude
            'O' => 38, // link_gmaps
            'P' => 14, // jumlah_mushaf_a5
            'Q' => 14, // jumlah_mushaf_a6
            'R' => 12, // jumlah_iqra
            'S' => 12, // urgensi
            'T' => 44, // urgensi_request
            'U' => 18, // sumber_info
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $spreadsheet = $event->sheet->getDelegate()->getParent();
                $event->sheet->getDelegate()->setTitle('Data');

                $this->tambahLembarPanduan($spreadsheet);

                // Berkas yang diunduh dibuka pada lembar "Data", bukan panduan.
                $spreadsheet->setActiveSheetIndex(0);
            },
        ];
    }

    /**
     * Lembar kedua berisi penjelasan tiap kolom, supaya pengisi tidak perlu
     * menebak — termasuk kolom mana yang wajib dan mana yang boleh dikosongkan.
     *
     * Lembar ini TIDAK ikut terbaca saat import: pengimpornya tidak memakai
     * WithMultipleSheets, dan pembaca hanya memproses lembar pertama.
     */
    private function tambahLembarPanduan($spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Panduan Kolom');

        $baris = [
            ['Kolom', 'Wajib', 'Keterangan', 'Contoh'],
            ['nama_lembaga', 'Ya', 'Nama lengkap lembaga/penerima manfaat.', 'TPQ Al Falah'],
            ['nama_penanggung_jawab_1', 'Ya', 'Nama orang yang bisa dihubungi.', 'Latifah'],
            ['nomor_hp', 'Ya', 'Nomor HP/WhatsApp aktif. Boleh ditulis 08xx atau 62xx.', '081234567890'],
            ['jabatan_penanggung_jawab_1', 'Tidak', 'Jabatan pengurus 1. Bila kosong diisi "Penanggung Jawab".', 'Ketua'],
            ['kategori_lembaga', 'Tidak', 'Pilih salah satu: '.implode(' / ', self::KATEGORI).'. Bila kosong diisi "Lembaga Lainnya".', 'TPQ/TPA/Madin'],
            ['provinsi', 'Tidak', 'Nama provinsi. Kosongkan bila ingin diuraikan otomatis dari link_gmaps.', 'Jawa Timur'],
            ['kota_kabupaten', 'Tidak', 'Nama kabupaten/kota. Kosongkan bila ingin diuraikan otomatis.', 'Kabupaten Blitar'],
            ['kecamatan', 'Tidak', 'Nama kecamatan, tanpa awalan "Kec.". Bila diisi, dipakai apa adanya.', 'Garum'],
            ['kelurahan_desa', 'Tidak', 'Nama kelurahan/desa. Isi hanya bila yakin — salah isi tidak akan terdeteksi.', 'Contoh Kelurahan'],
            ['kode_pos', 'Tidak', 'Kode pos, maksimal 10 karakter.', '66181'],
            ['alamat_detail', 'Ya', 'Jalan, nomor rumah, RT/RW, nama perumahan/lingkungan.', 'Jl. Contoh No. 1, RT.2/RW.5'],
            ['alamat_lengkap', 'Tidak', 'Alamat lengkap dalam satu baris. Isi ini bila tidak mau memecah alamat per kolom wilayah.', 'Jl. Contoh No. 1, Garum, Blitar'],
            ['latitude', 'Tidak', 'Koordinat lintang. Kosongkan bila link_gmaps sudah diisi.', '-8.0868357'],
            ['longitude', 'Tidak', 'Koordinat bujur. Kosongkan bila link_gmaps sudah diisi.', '112.2396983'],
            ['link_gmaps', 'Tidak', 'Tautan lokasi dari Google Maps. Bentuk pendek (maps.app.goo.gl) juga bisa. Tautan pada contoh HANYA peraga — ganti dengan tautan lokasi Anda sendiri.', 'https://maps.app.goo.gl/xxxx'],
            ['jumlah_mushaf_a5', 'Ya*', 'Jumlah mushaf ukuran A5. *Jumlah wajib ada: minimal salah satu dari A5/A6/Iqra lebih dari 0.', '100'],
            ['jumlah_mushaf_a6', 'Ya*', 'Jumlah mushaf ukuran A6. Isi 0 bila tidak ada.', '0'],
            ['jumlah_iqra', 'Ya*', 'Jumlah buku Iqra. Isi 0 bila tidak ada.', '0'],
            ['urgensi', 'Tidak', 'Tingkat urgensi: rendah / sedang / tinggi / mendesak. Kosong = sedang.', 'sedang'],
            ['urgensi_request', 'Tidak', 'Ceritakan kenapa mengajukan permintaan Qur\'an. Tampil di halaman detail sebagai "Urgensi Permintaan".', "Qur'an kami rusak terkena banjir"],
            ['sumber_info', 'Tidak', 'Dari mana permohonan ini diketahui.', 'WhatsApp'],

            ['', '', '', ''],
            ['PENTING', '', '', ''],
            ['Hapus baris contoh', '', 'Dua baris di atas (berwarna hijau) hanya CONTOH. HAPUS keduanya sebelum berkas di-import, atau akan ikut masuk sebagai data.', ''],
            ['Baris kosong', '', 'Baris kosong diabaikan. Jumlah baris boleh berapa saja.', ''],
            ['Satu baris = satu lembaga', '', 'Jangan menggabungkan dua lembaga dalam satu baris (mis. dipisah koma).', ''],
            ['Jangan ubah nama kolom', '', 'Nama kolom pada baris 1 lembar "Data" dipakai untuk mencocokkan data. Mengubahnya membuat baris gagal.', ''],
            ['Hanya lembar Data', '', 'Import hanya membaca lembar "Data". Lembar ini beserta lembar lain diabaikan.', ''],
            ['Bila gagal', '', 'Baris yang gagal TIDAK ikut masuk. Setelah import, penyebabnya ditampilkan: baris ke berapa dan kenapa.', ''],
            ['Nomor unik', '', 'Nomor permintaan (REQ-...) dibuat otomatis. Jangan diisi.', ''],
        ];

        $sheet->fromArray($baris, null, 'A1');

        $lebar = ['A' => 28, 'B' => 9, 'C' => 68, 'D' => 40];
        foreach ($lebar as $kolom => $nilai) {
            $sheet->getColumnDimension($kolom)->setWidth($nilai);
        }

        $terakhir = count($baris);
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A1:D{$terakhir}")->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
        ]);
    }
}
