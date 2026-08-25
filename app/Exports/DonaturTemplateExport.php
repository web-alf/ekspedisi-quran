<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DonaturTemplateExport implements FromArray, WithColumnWidths, WithHeadings, WithStyles
{
    public function array(): array
    {
        return [
            [
                'DN-001',
                'Ahmad Fauzi',
                '+628123456789',
                'ahmad@example.com',
                'Jl. Mawar No. 1, Jakarta',
                2,
                1,
                0,
                '2024-01-15',
                'Semoga bermanfaat',
            ],
            [
                'DN-002',
                'Siti Aminah',
                '+628987654321',
                'siti@example.com',
                'Jl. Melati No. 5, Bandung',
                1,
                0,
                2,
                '2024-02-20',
                'Untuk keluarga besar',
            ],
            [
                'DN-003',
                'Budi Santoso',
                '+628111223344',
                'budi@example.com',
                'Jl. Kenanga No. 10, Surabaya',
                3,
                2,
                1,
                '2024-03-10',
                null,
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
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 25,
            'C' => 20,
            'D' => 25,
            'E' => 35,
            'F' => 12,
            'G' => 12,
            'H' => 14,
            'I' => 16,
            'J' => 30,
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
            'A:J' => [
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true,
                ],
            ],
        ];
    }
}
