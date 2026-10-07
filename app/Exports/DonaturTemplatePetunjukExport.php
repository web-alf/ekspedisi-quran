<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lembar petunjuk yang menemani templat impor donatur.
 *
 * Tanpa ini, tim entry harus menebak sendiri bahwa jumlah_a5 berarti jumlah mushaf
 * (bukan dus), dan bahwa satu donatur boleh ditulis beberapa baris. Salah tebak di
 * situ berujung pada data yang masuk tetapi salah — bukan pesan galat.
 */
class DonaturTemplatePetunjukExport implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Petunjuk';
    }

    public function array(): array
    {
        $baris = [
            ['PETUNJUK PENGISIAN TEMPLAT IMPOR DONATUR'],
            [''],
            ['Isi data di lembar "Donatur". Lembar ini hanya penjelasan — tidak perlu diubah.'],
            [''],
            ['ATURAN UTAMA'],
            ['1. Satu baris = satu baris data. Jumlah A5/A6/Iqra menyatakan berapa mushaf PADA BARIS ITU.'],
            ['2. Satu donatur boleh ditulis BEBERAPA BARIS. Isi kolom kode_donatur yang sama di tiap baris.'],
            ['3. Kalau mushafnya seragam, cukup SATU BARIS walau jumlahnya banyak (50 mushaf = 1 baris, jumlah_a5 = 50).'],
            ['4. Baris ke-2 dan seterusnya boleh mengosongkan nama_donatur, no_hp, email, alamat, dan donation_date.'],
            ['5. Jangan mengubah nama kolom di baris pertama.'],
            [''],
            ['CONTOH: mushaf yang berbeda-beda'],
            ['  DN-001 ditulis 2 baris karena mushafnya untuk 2 orang yang berbeda:'],
            ['    baris 1 -> jumlah_a5=10, wakif_name="Alm. H. Ahmad Subarjo", relationship_to_donatur="Almarhum"'],
            ['    baris 2 -> jumlah_a5=5,  wakif_name="Ibu Siti Aminah",     relationship_to_donatur="Keluarga"'],
            ['  Hasilnya: 1 donatur, 15 mushaf. Nomor A5 berjalan 1-10 lalu 11-15.'],
            [''],
            ['CONTOH: mushaf yang seragam'],
            ['  DN-002 cukup 1 baris walau 6 mushaf: jumlah_a5=3, jumlah_a6=2, jumlah_iqra=1'],
            ['  wakif_name dikosongkan -> nama wakif diisi nama donatur, seperti perilaku lama.'],
            [''],
            ['ARTI SETIAP KOLOM'],
            ['kode_donatur', 'WAJIB. Kode donatur. Kode yang sama = donatur yang sama (tidak digandakan).'],
            ['nama_donatur', 'WAJIB di baris pertama. Nama donatur.'],
            ['no_hp', 'WAJIB di baris pertama. Boleh 0812…, 62812…, atau +62812… — semuanya diseragamkan.'],
            ['email_donatur', 'Opsional.'],
            ['alamat_donatur', 'Opsional.'],
            ['jumlah_a5', 'Berapa mushaf A5 pada baris ini. Kosongkan atau 0 kalau tidak ada.'],
            ['jumlah_a6', 'Berapa mushaf A6 pada baris ini.'],
            ['jumlah_iqra', 'Berapa mushaf Iqra pada baris ini.'],
            ['donation_date', 'WAJIB di baris pertama. Format 2024-01-15 atau 15/01/2024.'],
            ['doa_untuk_semua', 'Opsional. Satu doa untuk SELURUH mushaf donatur ini.'],
            ['wakif_name', 'Opsional. Nama wakif untuk mushaf pada baris ini. Kosong = nama donatur.'],
            ['doa_request', 'Opsional. Doa untuk mushaf pada baris ini. Kosong = pakai doa_untuk_semua.'],
            ['relationship_to_donatur', 'Opsional. Hubungan wakif dengan donatur, mis. Diri sendiri, Keluarga, Almarhum.'],
            [''],
            ['CATATAN'],
            ['- Jumlah A5/A6/Iqra = jumlah MUSHAF, bukan jumlah dus.'],
            ['- Kolom jumlah yang dikosongkan dianggap 0.'],
            ['- Kode donatur yang sudah ada akan DITAMBAH, bukan dibuat ulang.'],
            ['- Sebelum import dijalankan, sistem menampilkan ringkasan hasil pembacaan file untuk diperiksa.'],
        ];

        return $baris;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 90,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF065F46']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFD1FAE5'],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ],
            5 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FF10B981']],
            ],
            12 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FF10B981']],
            ],
            18 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FF10B981']],
            ],
            21 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FF10B981']],
            ],
            36 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FF10B981']],
            ],
            'A:B' => [
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            ],
        ];
    }
}
