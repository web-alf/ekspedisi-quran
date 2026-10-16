<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Faq;
use App\Models\User;

class FaqPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::SETTINGS_READ->value);
    }

    public function view(User $user, Faq $faq): bool
    {
        return $user->can(PermissionEnum::SETTINGS_READ->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::SETTINGS_WRITE->value);
    }

    public function update(User $user, Faq $faq): bool
    {
        return $user->can(PermissionEnum::SETTINGS_WRITE->value);
    }

    public function delete(User $user, Faq $faq): bool
    {
        return $user->can(PermissionEnum::SETTINGS_DELETE->value);
    }

    public function restore(User $user, Faq $faq): bool
    {
        return false;
    }

    public function forceDelete(User $user, Faq $faq): bool
    {
        return false;
    }
}
