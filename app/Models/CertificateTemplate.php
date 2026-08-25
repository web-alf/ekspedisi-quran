<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CertificateTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'template_file_path',
        'field_positions',
        'width',
        'height',
        'is_active',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'field_positions' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });

        static::saved(function ($model) {
            // Ensure only one default template
            if ($model->is_default) {
                static::where('id', '!=', $model->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * Relationship: Template belongs to User (creator)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Template has many certificates
     */
    public function certificates()
    {
        return $this->hasMany(Sertifikat::class, 'template_id');
    }

    /**
     * Scope: Active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Default template
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get default field positions - HANYA 2 field yang dibutuhkan
     */
    public static function getDefaultFieldPositions()
    {
        return [
            'wakif_name' => ['x' => 400, 'y' => 300, 'font_size' => 24, 'color' => '#2d5016'],
            'hijri_date' => ['x' => 300, 'y' => 400, 'font_size' => 18, 'color' => '#000000'],
        ];
    }

    /**
     * Get available fields for positioning - HANYA 2 field yang dibutuhkan
     */
    public static function getAvailableFields()
    {
        return [
            'wakif_name' => 'Nama Wakif',
            'hijri_date' => 'Tanggal Donasi Hijriyah',
        ];
    }

    /**
     * Get field position for specific field
     */
    public function getFieldPosition($fieldName)
    {
        return $this->field_positions[$fieldName] ?? null;
    }

    /**
     * Update field position
     */
    public function updateFieldPosition($fieldName, $x, $y, ?float $fontSize = null, ?string $color = null)
    {
        $positions = $this->field_positions;

        $positions[$fieldName] = [
            'x' => (int) $x,
            'y' => (int) $y,
            'font_size' => $fontSize ?? ($positions[$fieldName]['font_size'] ?? 12),
            'color' => $color ?? ($positions[$fieldName]['color'] ?? '#000000'),
        ];

        $this->update(['field_positions' => $positions]);
    }

    /**
     * Get template file full path
     */
    public function getFullTemplatePath()
    {
        return storage_path('app/public/'.$this->template_file_path);
    }

    /**
     * Get template public URL
     */
    public function getTemplateUrl()
    {
        return asset('storage/'.$this->template_file_path);
    }

    /**
     * Check if template file exists
     */
    public function templateFileExists()
    {
        return file_exists($this->getFullTemplatePath());
    }

    /**
     * Get template image info
     */
    public function getTemplateImageInfo()
    {
        if (! $this->templateFileExists()) {
            return null;
        }

        $imagePath = $this->getFullTemplatePath();
        $imageInfo = getimagesize($imagePath);

        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'type' => $imageInfo[2],
            'mime' => $imageInfo['mime'],
            'size' => filesize($imagePath),
        ];
    }

    /**
     * Get preview URL for template
     */
    public function getPreviewUrlAttribute()
    {
        return route('admin.certificate-templates.preview', $this->id);
    }

    /**
     * Get download URL for template
     */
    public function getDownloadUrlAttribute()
    {
        return route('admin.certificate-templates.download', $this->id);
    }
}
