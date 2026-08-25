<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SettingsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $settings;

    public function __construct($settings)
    {
        $this->settings = $settings;
    }

    public function collection()
    {
        return $this->settings;
    }

    public function headings(): array
    {
        return [
            'Key',
            'Label',
            'Value',
            'Type',
            'Description',
            'Options',
            'Group',
            'Sort Order',
            'Is Public',
            'Is Active'
        ];
    }

    public function map($setting): array
    {
        return [
            $setting->key,
            $setting->label,
            $setting->value,
            $setting->type,
            $setting->description,
            json_encode($setting->options),
            $setting->group,
            $setting->sort_order,
            $setting->is_public ? 'Ya' : 'Tidak',
            $setting->is_active ? 'Ya' : 'Tidak'
        ];
    }
}