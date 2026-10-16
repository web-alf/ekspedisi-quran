<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Gallery;
use App\Models\User;

class GalleryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::SETTINGS_READ->value);
    }

    public function view(User $user, Gallery $gallery): bool
    {
        return $user->can(PermissionEnum::SETTINGS_READ->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::SETTINGS_WRITE->value);
    }

    public function update(User $user, Gallery $gallery): bool
    {
        return $user->can(PermissionEnum::SETTINGS_WRITE->value);
    }

    public function delete(User $user, Gallery $gallery): bool
    {
        return $user->can(PermissionEnum::SETTINGS_DELETE->value);
    }

    public function restore(User $user, Gallery $gallery): bool
    {
        return false;
    }

    public function forceDelete(User $user, Gallery $gallery): bool
    {
        return false;
    }
}
