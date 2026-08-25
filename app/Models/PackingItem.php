<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'packing_box_id',
        'pengiriman_id',
        'packed_by',
        'packed_at',
        'urutan_dalam_box',
        'scan_method',
        'quantity'
    ];

    protected $casts = [
        'packed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function packingBox()
    {
        return $this->belongsTo(PackingBox::class);
    }

    public function pengiriman()
    {
        return $this->belongsTo(Pengiriman::class);
    }

    public function packedByUser()
    {
        return $this->belongsTo(User::class, 'packed_by');
    }
}