<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Testimonial;
use App\Models\User;

class TestimonialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::SETTINGS_READ->value);
    }

    public function view(User $user, Testimonial $testimonial): bool
    {
        return $user->can(PermissionEnum::SETTINGS_READ->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::SETTINGS_WRITE->value);
    }

    public function update(User $user, Testimonial $testimonial): bool
    {
        return $user->can(PermissionEnum::SETTINGS_WRITE->value);
    }

    public function delete(User $user, Testimonial $testimonial): bool
    {
        return $user->can(PermissionEnum::SETTINGS_DELETE->value);
    }

    public function toggle(User $user, Testimonial $testimonial): bool
    {
        return $user->can(PermissionEnum::SETTINGS_WRITE->value);
    }

    public function restore(User $user, Testimonial $testimonial): bool
    {
        return false;
    }

    public function forceDelete(User $user, Testimonial $testimonial): bool
    {
        return false;
    }
}
