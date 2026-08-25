<?php

namespace App\Imports;

use App\Models\Setting;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SettingsImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        return Setting::updateOrCreate(
            ['key' => $row['key']],
            [
                'label' => $row['label'],
                'value' => $row['value'],
                'type' => $row['type'],
                'description' => $row['description'] ?? null,
                'options' => !empty($row['options']) ? json_decode($row['options'], true) : null,
                'group' => $row['group'] ?? 'general',
                'sort_order' => $row['sort_order'] ?? 0,
                'is_public' => $this->parseBoolean($row['is_public'] ?? false),
                'is_active' => $this->parseBoolean($row['is_active'] ?? true)
            ]
        );
    }

    public function rules(): array
    {
        return [
            'key' => 'required|string',
            'label' => 'required|string',
            'type' => 'required|in:text,textarea,number,boolean,image,file,select',
            'group' => 'nullable|string'
        ];
    }

    private function parseBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $value = strtolower($value);
            return in_array($value, ['yes', 'ya', '1', 'true']);
        }
        
        return (bool) $value;
    }
}