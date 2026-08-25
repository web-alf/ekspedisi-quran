<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Pengiriman;
use App\Models\User;

class PengirimanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::SHIPMENTS_READ->value);
    }

    public function view(User $user, Pengiriman $pengiriman): bool
    {
        return $user->can(PermissionEnum::SHIPMENTS_READ->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::SHIPMENTS_CREATE->value);
    }

    public function update(User $user, Pengiriman $pengiriman): bool
    {
        return $user->can(PermissionEnum::SHIPMENTS_UPDATE->value);
    }

    public function delete(User $user, Pengiriman $pengiriman): bool
    {
        return $user->can(PermissionEnum::SHIPMENTS_DELETE->value);
    }

    public function export(User $user): bool
    {
        return $user->can(PermissionEnum::SHIPMENTS_EXPORT->value);
    }

    public function track(User $user): bool
    {
        return $user->can(PermissionEnum::SHIPMENTS_TRACK->value);
    }

    public function bulkUpdate(User $user): bool
    {
        return $user->can(PermissionEnum::SHIPMENTS_BULK_UPDATE->value);
    }

    public function updateStatus(User $user, Pengiriman $pengiriman): bool
    {
        return $user->can(PermissionEnum::SHIPMENTS_UPDATE_STATUS->value);
    }

    public function restore(User $user, Pengiriman $pengiriman): bool
    {
        return false;
    }

    public function forceDelete(User $user, Pengiriman $pengiriman): bool
    {
        return false;
    }
}
