<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPerformance extends Model
{
    use HasFactory;

    protected $table = 'user_performance';

    protected $fillable = [
        'user_id',
        'bulan',
        'total_target',
        'total_achieved',
        'achievement_rate',
        'total_hari_kerja',
        'total_hari_complete',
        'total_carry_over',
        'warning_count',
        'avg_completion_time'
    ];

    protected $casts = [
        'achievement_rate' => 'decimal:2',
        'avg_completion_time' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeByMonth($query, $month)
    {
        return $query->where('bulan', $month);
    }

    public function scopeHighPerformers($query, $minRate = 90)
    {
        return $query->where('achievement_rate', '>=', $minRate);
    }

    public function scopeLowPerformers($query, $maxRate = 70)
    {
        return $query->where('achievement_rate', '<', $maxRate);
    }

    /**
     * Accessors
     */
    public function getPerformanceLevelAttribute()
    {
        if ($this->achievement_rate >= 95) return 'excellent';
        if ($this->achievement_rate >= 85) return 'good';
        if ($this->achievement_rate >= 70) return 'average';
        return 'needs_improvement';
    }

    public function getPerformanceBadgeAttribute()
    {
        $levels = [
            'excellent' => ['text' => 'Excellent', 'class' => 'bg-green-100 text-green-800'],
            'good' => ['text' => 'Good', 'class' => 'bg-blue-100 text-blue-800'],
            'average' => ['text' => 'Average', 'class' => 'bg-yellow-100 text-yellow-800'],
            'needs_improvement' => ['text' => 'Needs Improvement', 'class' => 'bg-red-100 text-red-800'],
        ];
        
        return $levels[$this->performance_level];
    }
}