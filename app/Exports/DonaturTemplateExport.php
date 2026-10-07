<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lembar data templat impor donatur.
 *
 * Contoh barisnya sengaja menunjukkan dua cara pengisian sekaligus:
 *   - DN-001 ditulis DUA BARIS karena mushafnya berbeda nama wakif dan doanya,
 *   - DN-002 ditulis SATU BARIS walau 6 mushaf, karena semuanya sama.
 */
class DonaturTemplateExport implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Donatur';
    }

    public function array(): array
    {
        return [
            // Dua baris untuk satu donatur: mushafnya berbeda nama wakif & doa.
            [
                'DN-001', 'Ahmad Fauzi', '+628****6789', 'ahmad@example.com',
                'Jl. Mawar No. 1, Jakarta',
                10, 0, 0,
                '2024-01-15', null,
                'Alm. H. Ahmad Subarjo', 'Semoga diampuni dosanya', 'Almarhum',
            ],
            [
                'DN-001', null, null, null, null,
                5, 0, 0,
                null, null,
                'Ibu Siti Aminah', 'Semoga lekas sembuh', 'Keluarga',
            ],
            // Satu baris walau 6 mushaf, karena nama wakifnya sama semua.
            [
                'DN-002', 'Budi Santoso', '+628****3344', 'budi@example.com',
                'Jl. Kenanga No. 10, Surabaya',
                3, 2, 1,
                '2024-03-10', 'Semoga bermanfaat untuk semua',
                null, null, null,
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'kode_donatur',
            'nama_donatur',
            'no_hp',
            'email_donatur',
            'alamat_donatur',
            'jumlah_a5',
            'jumlah_a6',
            'jumlah_iqra',
            'donation_date',
            'doa_untuk_semua',
            'wakif_name',
            'doa_request',
            'relationship_to_donatur',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 22,
            'C' => 18,
            'D' => 24,
            'E' => 30,
            'F' => 11,
            'G' => 11,
            'H' => 13,
            'I' => 15,
            'J' => 28,
            'K' => 26,
            'L' => 28,
            'M' => 22,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF10B981'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            'A:M' => [
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true,
                ],
            ],
        ];
    }
}
