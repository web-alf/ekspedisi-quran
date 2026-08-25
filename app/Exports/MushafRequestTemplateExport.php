<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MushafRequestTemplateExport implements FromArray, WithColumnWidths, WithHeadings, WithStyles
{
    public function array(): array
    {
        // Return sample data rows - minimal format
        return [
            [
                'TPQ Al Falah Plosorejo',
                'Latifah',
                '085731507971',
                'Lingkungan, Plosorejo RT.2/RW.5, Bence, Kec. Garum, Blitar',
                'https://maps.app.goo.gl/na4EG41yECawFzka8',
                10,
                'Banyak Al-Qur\'an yang sudah rusak',
            ],
            [
                'Yayasan Al Hikmah Peduli',
                'Tri Handayani',
                '081553843650',
                'Perum Gardenia G1, Bence, Kec. Garum, Blitar',
                'https://maps.app.goo.gl/NNkfrnA5V4goEypTA',
                500,
                'Untuk kebutuhan Al-Qur\'an saat naik jilid',
            ],
            [
                'MI Darul Huda Bence',
                'Arzuq Fanani Zen',
                '085649645815',
                'Jl. Slorok, RT.2/RW.1, Lingkungan Tanggung, Bence, Kec. Garum, Blitar',
                'https://maps.app.goo.gl/uXgwGX5nAtuyiSPJ9',
                181,
                'Untuk pembelajaran disekolah sejumlah siswa',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'nama_lembaga',
            'nama_penanggung_jawab_1',
            'nomor_hp',
            'alamat_lengkap',
            'link_gmaps',
            'jumlah_kebutuhan_mushaf',
            'urgensi',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style untuk header row
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4CAF50'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
            // Style untuk sample data rows
            '2:4' => [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8F5E9'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30, // nama_lembaga
            'B' => 25, // nama_penanggung_jawab_1
            'C' => 18, // nomor_hp
            'D' => 50, // alamat_lengkap
            'E' => 40, // link_gmaps
            'F' => 30, // jumlah_kebutuhan_mushaf
            'G' => 45, // urgensi
        ];
    }
}
