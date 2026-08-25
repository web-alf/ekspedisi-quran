<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role', // Keep for backward compatibility during migration
        'role_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get user's primary role name (backward compatibility)
     *
     * NOTE: Returns only the FIRST role for backward compatibility.
     * Use getRolesArrayAttribute() for multiple roles support.
     */
    public function getRoleAttribute()
    {
        return $this->roles->first()?->name;
    }

    /**
     * Get all user's role names as array
     *
     * Use this for multi-role support instead of getRoleAttribute()
     */
    public function getRolesArrayAttribute()
    {
        return $this->roles->pluck('name')->toArray();
    }

    /**
     * Get user's role display name
     */
    public function getRoleDisplayAttribute()
    {
        $role = $this->roles->first();
        if (!$role) {
            return 'No Role';
        }

        // Use role's name directly from database
        // For custom display names, add 'display_name' column to roles table
        return $role->display_name ?? $role->name;
    }

    /**
     * Get all user's role display names as array
     */
    public function getRolesDisplayAttribute()
    {
        // Use role's display_name or fallback to name
        return $this->roles->map(function($role) {
            return $role->display_name ?? $role->name;
        })->toArray();
    }

    /**
     * 📦 Core Relationships
     */
    public function pengiriman()
    {
        return $this->hasMany(Pengiriman::class, 'created_by');
    }

    public function donatur()
    {
        return $this->hasMany(Donatur::class, 'created_by');
    }

    public function wakafBatches()
    {
        return $this->hasMany(WakafBatch::class, 'created_by');
    }

    public function wakafItems()
    {
        return $this->hasMany(WakafItem::class, 'created_by');
    }

    /**
     * 📦 Warehouse Relationships
     */
    public function dailyPackingTasks()
    {
        return $this->hasMany(DailyPackingTask::class);
    }

    public function packingNotifications()
    {
        return $this->hasMany(PackingNotification::class);
    }

    public function userPerformance()
    {
        return $this->hasMany(UserPerformance::class);
    }

    public function packedItems()
    {
        return $this->hasMany(PackingItem::class, 'packed_by');
    }

    public function packingBoxesCreated()
    {
        return $this->hasMany(PackingBox::class, 'created_by_supervisor');
    }

    /**
     * 📦 Warehouse Scopes
     */
    public function scopeWarehouseUsers($query)
    {
        return $query->permission('warehouse.dashboard');
    }

    /**
     * 📦 Warehouse Helper Methods
     */
    public function getTodayTask()
    {
        return $this->dailyPackingTasks()
            ->whereDate('tanggal_tugas', today())
            ->first();
    }

    public function getUnreadNotifications()
    {
        return $this->packingNotifications()
            ->unread()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getCurrentMonthPerformance()
    {
        return $this->userPerformance()
            ->byMonth(now()->format('Y-m'))
            ->first();
    }
}
