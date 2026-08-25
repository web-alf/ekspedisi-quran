<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Sertifikat;
use App\Models\User;

class SertifikatPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::CERTIFICATES_READ->value);
    }

    public function view(User $user, Sertifikat $sertifikat): bool
    {
        return $user->can(PermissionEnum::CERTIFICATES_READ->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CERTIFICATES_CREATE->value);
    }

    public function update(User $user, Sertifikat $sertifikat): bool
    {
        return $user->can(PermissionEnum::CERTIFICATES_UPDATE->value);
    }

    public function delete(User $user, Sertifikat $sertifikat): bool
    {
        return $user->can(PermissionEnum::CERTIFICATES_DELETE->value);
    }

    public function generate(User $user): bool
    {
        return $user->can(PermissionEnum::CERTIFICATES_GENERATE->value);
    }

    public function download(User $user, Sertifikat $sertifikat): bool
    {
        return $user->can(PermissionEnum::CERTIFICATES_DOWNLOAD->value);
    }

    public function restore(User $user, Sertifikat $sertifikat): bool
    {
        return false;
    }

    public function forceDelete(User $user, Sertifikat $sertifikat): bool
    {
        return false;
    }
}
