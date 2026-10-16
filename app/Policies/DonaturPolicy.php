<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Donatur;
use App\Models\User;

class DonaturPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::DONATUR_READ->value);
    }

    public function view(User $user, Donatur $donatur): bool
    {
        return $user->can(PermissionEnum::DONATUR_READ->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::DONATUR_CREATE->value);
    }

    public function update(User $user, Donatur $donatur): bool
    {
        return $user->can(PermissionEnum::DONATUR_UPDATE->value);
    }

    public function delete(User $user, Donatur $donatur): bool
    {
        return $user->can(PermissionEnum::DONATUR_DELETE->value);
    }

    public function import(User $user): bool
    {
        return $user->can(PermissionEnum::DONATUR_IMPORT->value);
    }

    public function export(User $user): bool
    {
        return $user->can(PermissionEnum::DONATUR_EXPORT->value);
    }

    public function restore(User $user, Donatur $donatur): bool
    {
        return false;
    }

    public function forceDelete(User $user, Donatur $donatur): bool
    {
        return false;
    }
}
