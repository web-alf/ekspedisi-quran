<?php

namespace App\Exports;

use App\Models\Donatur;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DonaturExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Donatur::query()->orderBy('created_at', 'desc');

        if (isset($this->filters['search']) && $this->filters['search']) {
            $query->byDonatur($this->filters['search']);
        }

        if (isset($this->filters['kode_donatur']) && $this->filters['kode_donatur']) {
            $query->where('kode_donatur', 'like', '%'.$this->filters['kode_donatur'].'%');
        }

        if (isset($this->filters['start_date']) && $this->filters['start_date']) {
            $query->whereDate('donation_date', '>=', $this->filters['start_date']);
        }

        if (isset($this->filters['end_date']) && $this->filters['end_date']) {
            $query->whereDate('donation_date', '<=', $this->filters['end_date']);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Kode Donatur',
            'Nama Donatur',
            'Nomor HP',
            'Email',
            'Alamat',
            'Jumlah A5',
            'Jumlah A6',
            'Jumlah IQRA',
            'Total Mushaf',
            'Jenis Wakaf Dipilih',
            'Mode Doa',
            'Doa Untuk Semua',
            'Tanggal Donasi',
            'Jumlah Donasi',
            'Tanggal Dibuat',
        ];
    }

    public function map($donatur): array
    {
        return [
            $donatur->kode_donatur,
            $donatur->nama_donatur,
            $donatur->no_hp,
            $donatur->email_donatur,
            $donatur->alamat_donatur,
            $donatur->total_a5_count ?? 0,
            $donatur->total_a6_count ?? 0,
            $donatur->total_iqra_count ?? 0,
            ($donatur->total_a5_count ?? 0) + ($donatur->total_a6_count ?? 0) + ($donatur->total_iqra_count ?? 0),
            is_array($donatur->jenis_wakaf_dipilih) ? implode(', ', $donatur->jenis_wakaf_dipilih) : $donatur->jenis_wakaf_dipilih,
            $this->getPrayerModeText($donatur->prayer_mode),
            $donatur->doa_untuk_semua,
            $donatur->donation_date ? $donatur->donation_date->format('d/m/Y') : '',
            $donatur->donation_count ?? 1,
            $donatur->created_at ? $donatur->created_at->format('d/m/Y H:i') : '',
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
                    'startColor' => ['argb' => 'FF3B82F6'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            'A:O' => [
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    private function getPrayerModeText(?string $mode): string
    {
        return match ($mode) {
            'semua_donatur' => 'Semua atas nama donatur',
            'customize_individual' => 'Kustomisasi individual',
            'mixed' => 'Campuran',
            default => $mode ?? '-',
        };
    }
}
