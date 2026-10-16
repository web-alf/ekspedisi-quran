<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\CertificateTemplate;
use App\Models\User;

class CertificateTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::TEMPLATES_READ->value);
    }

    public function view(User $user, CertificateTemplate $certificateTemplate): bool
    {
        return $user->can(PermissionEnum::TEMPLATES_READ->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::TEMPLATES_CREATE->value);
    }

    public function update(User $user, CertificateTemplate $certificateTemplate): bool
    {
        return $user->can(PermissionEnum::TEMPLATES_UPDATE->value);
    }

    public function delete(User $user, CertificateTemplate $certificateTemplate): bool
    {
        return $user->can(PermissionEnum::TEMPLATES_DELETE->value);
    }

    public function setDefault(User $user, CertificateTemplate $certificateTemplate): bool
    {
        return $user->can(PermissionEnum::TEMPLATES_SET_DEFAULT->value);
    }

    public function toggle(User $user, CertificateTemplate $certificateTemplate): bool
    {
        return $user->can(PermissionEnum::TEMPLATES_TOGGLE->value);
    }

    public function restore(User $user, CertificateTemplate $certificateTemplate): bool
    {
        return false;
    }

    public function forceDelete(User $user, CertificateTemplate $certificateTemplate): bool
    {
        return false;
    }
}
