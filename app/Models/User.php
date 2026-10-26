<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleEnum;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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
        'manager_id',
        'gudang_id',
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
     * Manager distribusi yang membawahi pengguna ini.
     *
     * Ada supaya hubungan "kurir di bawah manager" melekat pada DATA, bukan pada
     * asumsi bahwa semua manager membawahi semua kurir. Tanpa ini, keempat
     * manager tidak bisa dibedakan satu sama lain.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Pengguna yang berada di bawah manager ini.
     */
    public function bawahan(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    /**
     * Gudang utama akun (Fase 2 rombakan gudang).
     *
     * Dipakai SPV Gudang: wewenangnya dipasangkan dengan gudang ini, dan tugas
     * packing yang ia assign kepada freelancer menandahi gudang itu. Super-admin
     * tidak memakainya; NULL berarti akun tidak terpasang di gudang tertentu.
     */
    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    /**
     * Boleh memindahkan akun SPV/staf gudang ke Semarang/Boyolali?
     *
     * Keputusan pemilik sistem (16/10/2026): super-admin dan manager gudang (PJ,
     * role `manager-repacking`). SPV di produksi IKUT memegang role manager-repacking,
     * jadi pemegang `spv-gudang` dikecualikan — SPV tidak boleh memindahkan dirinya
     * sendiri atau SPV gudang lain.
     */
    public function bolehAturPenempatanGudang(): bool
    {
        if ($this->hasRole(RoleEnum::SUPER_ADMIN->value)) {
            return true;
        }

        return $this->hasRole(RoleEnum::MANAGER_REPACKING->value)
            && ! $this->hasRole(RoleEnum::SPV_GUDANG->value);
    }

    /**
     * Apakah pengguna ini manager distribusi.
     */
    public function adalahManager(): bool
    {
        return $this->hasRole(RoleEnum::MANAGER->value);
    }

    /**
     * Apakah pengguna ini kurir.
     */
    public function adalahKurir(): bool
    {
        return $this->hasRole(RoleEnum::COURIER->value);
    }

    /**
     * ID pengguna yang menjadi tanggung jawab pengguna ini.
     *
     * Manager mendapat bawahan langsungnya; selain manager mendapat dirinya
     * sendiri — sehingga pemanggil bisa memakai satu daftar tanpa bercabang:
     * manager melihat bawahannya, kurir melihat dirinya.
     *
     * @return array<int, int>
     */
    public function idBawahan(): array
    {
        if (! $this->adalahManager()) {
            return [$this->id];
        }

        return $this->bawahan()->pluck('id')->push($this->id)->all();
    }

    /**
     * Manager distribusi yang bisa dipilih sebagai atasan.
     *
     * @return Collection<int, User>
     */
    public static function daftarManager(): Collection
    {
        return static::query()
            ->whereHas('roles', fn ($q) => $q->where('name', RoleEnum::MANAGER->value))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /**
     * Get user's role display name
     */
    public function getRoleDisplayAttribute()
    {
        // Role UTAMA (paling luas wewenangnya), bukan baris pivot pertama: akun yang
        // memegang beberapa role (mis. PJ gudang yang juga memegang role warehouse sejak
        // data lama) tidak boleh tampil sebagai freelancer.
        $role = $this->roleUtama();
        if (! $role) {
            return 'No Role';
        }

        // Use role's name directly from database
        // For custom display names, add 'display_name' column to roles table
        return $role->display_name ?? $role->name;
    }

    /**
     * Role yang MEWAKILI akun di tabel/daftar pengguna.
     *
     * Satu akun bisa memegang beberapa role (mis. akun PJ gudang yang juga punya role
     * `warehouse` sejak data lama), dan `$this->roles->first()` mengikuti urutan baris
     * pivot — bukan wewenang. 26/10/2026: label `warehouse` berganti menjadi
     * "Freelancer Gudang" dan akun gudang@ (PJ) yang memegang dua role langsung tampil
     * sebagai freelancer di daftar pengguna. Urutan di bawah menaruh role terluas lebih
     * dulu, jadi yang tampil adalah role yang benar-benar mewakili akun itu.
     *
     * @var array<int, string>
     */
    public const URUTAN_TAMPIL_ROLE = [
        RoleEnum::SUPER_ADMIN->value,
        RoleEnum::MANAGER->value,
        // SPV di ATAS PJ: akun SPV Semarang/Boyolali memegang DUA role (spv-gudang +
        // manager-repacking), dan identitas kerjanya adalah SPV per gudang — menampilkan
        // "Manager Repacking" di akun bernama "SPV Semarang" justru mengaburkan siapa dia.
        RoleEnum::SPV_GUDANG->value,
        RoleEnum::MANAGER_REPACKING->value,
        RoleEnum::SUPERVISOR->value,
        RoleEnum::CUSTOMER_SERVICE->value,
        RoleEnum::DISTRIBUSI->value,
        RoleEnum::COURIER->value,
        RoleEnum::WAREHOUSE->value,
    ];

    /**
     * Role utama akun (paling luas wewenangnya), atau null bila belum punya role.
     */
    public function roleUtama(): ?Role
    {
        if ($this->roles->isEmpty()) {
            return null;
        }

        $urutan = array_flip(self::URUTAN_TAMPIL_ROLE);

        return $this->roles
            ->sortBy(fn (Role $role) => $urutan[$role->name] ?? PHP_INT_MAX)
            ->first();
    }

    /**
     * Get all user's role display names as array
     */
    public function getRolesDisplayAttribute()
    {
        // Use role's display_name or fallback to name
        return $this->roles->map(function ($role) {
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
