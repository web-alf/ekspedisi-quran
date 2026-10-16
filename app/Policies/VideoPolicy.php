<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\User;
use App\Models\Video;

class VideoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::SETTINGS_READ->value);
    }

    public function view(User $user, Video $video): bool
    {
        return $user->can(PermissionEnum::SETTINGS_READ->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::SETTINGS_WRITE->value);
    }

    public function update(User $user, Video $video): bool
    {
        return $user->can(PermissionEnum::SETTINGS_WRITE->value);
    }

    public function delete(User $user, Video $video): bool
    {
        return $user->can(PermissionEnum::SETTINGS_DELETE->value);
    }

    public function restore(User $user, Video $video): bool
    {
        return false;
    }

    public function forceDelete(User $user, Video $video): bool
    {
        return false;
    }
}
