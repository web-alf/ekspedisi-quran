<?php

namespace App\Exports;

use App\Models\MushafRequest;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MushafRequestExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = MushafRequest::with(['reviewer:id,name'])
            ->orderBy('created_at', 'desc');

        // Apply filters similar to the controller
        if (isset($this->filters['search']) && $this->filters['search']) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('no_request', 'like', '%'.$search.'%')
                    ->orWhere('nama_lembaga', 'like', '%'.$search.'%')
                    ->orWhere('nama_pengurus_1', 'like', '%'.$search.'%')
                    ->orWhere('alamat_lengkap', 'like', '%'.$search.'%')
                    ->orWhere('provinsi', 'like', '%'.$search.'%')
                    ->orWhere('kota_kabupaten', 'like', '%'.$search.'%');
            });
        }

        if (isset($this->filters['status']) && $this->filters['status']) {
            $query->where('status', $this->filters['status']);
        }

        if (isset($this->filters['start_date']) && $this->filters['start_date']) {
            $query->whereDate('created_at', '>=', $this->filters['start_date']);
        }

        if (isset($this->filters['end_date']) && $this->filters['end_date']) {
            $query->whereDate('created_at', '<=', $this->filters['end_date']);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'No Request',
            'Nama Lembaga',
            'Kategori Lembaga',
            'Alamat Lengkap',
            'Provinsi',
            'Kota/Kabupaten',
            'Kecamatan',
            'Kelurahan/Desa',
            'Kode Pos',
            'Nama Pengurus 1',
            'Jabatan Pengurus 1',
            'WhatsApp Pengurus 1',
            'Nama Pengurus 2',
            'Jabatan Pengurus 2',
            'WhatsApp Pengurus 2',
            'Jumlah Mushaf A5',
            'Jumlah Mushaf A6',
            'Jumlah IQRA',
            'Total Mushaf',
            'Jenis Mushaf',
            'Urgensi',
            'Sumber Info',
            'Status',
            'Catatan Admin',
            'Reviewer',
            'Tanggal Request',
            'Tanggal Approved/Rejected',
        ];
    }

    public function map($mushafRequest): array
    {
        return [
            $mushafRequest->no_request,
            $mushafRequest->nama_lembaga,
            $mushafRequest->kategori_lembaga,
            $mushafRequest->alamat_lengkap,
            $mushafRequest->provinsi,
            $mushafRequest->kota_kabupaten,
            $mushafRequest->kecamatan,
            $mushafRequest->kelurahan_desa,
            $mushafRequest->kode_pos,
            $mushafRequest->nama_pengurus_1,
            $mushafRequest->jabatan_pengurus_1,
            $mushafRequest->whatsapp_pengurus_1,
            $mushafRequest->nama_pengurus_2,
            $mushafRequest->jabatan_pengurus_2,
            $mushafRequest->whatsapp_pengurus_2,
            $mushafRequest->jumlah_mushaf_a5 ?? 0,
            $mushafRequest->jumlah_mushaf_a6 ?? 0,
            $mushafRequest->jumlah_iqra ?? 0,
            ($mushafRequest->jumlah_mushaf_a5 ?? 0) + ($mushafRequest->jumlah_mushaf_a6 ?? 0) + ($mushafRequest->jumlah_iqra ?? 0),
            $mushafRequest->jenis_mushaf_list,
            $mushafRequest->urgensi_request,
            $mushafRequest->sumber_info,
            $this->getStatusText($mushafRequest->status),
            $mushafRequest->catatan_admin,
            $mushafRequest->reviewer ? $mushafRequest->reviewer->name : '',
            $mushafRequest->created_at ? $mushafRequest->created_at->format('d/m/Y H:i') : '',
            $mushafRequest->approved_at ? $mushafRequest->approved_at->format('d/m/Y H:i') : ($mushafRequest->rejected_at ? $mushafRequest->rejected_at->format('d/m/Y H:i') : ''),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Header row styling
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF3B82F6'], // Blue background
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            // All cells alignment
            'A:Z' => [
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    private function getStatusText($status): string
    {
        return match($status) {
            'pending' => 'Menunggu',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'processing' => 'Diproses',
            'completed' => 'Selesai',
            default => $status
        };
    }
}