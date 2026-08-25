<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\MushafRequest;
use App\Models\User;

class MushafRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::MUSHAF_REQUESTS_READ->value);
    }

    public function view(User $user, MushafRequest $mushafRequest): bool
    {
        return $user->can(PermissionEnum::MUSHAF_REQUESTS_READ->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::MUSHAF_REQUESTS_CREATE->value);
    }

    public function update(User $user, MushafRequest $mushafRequest): bool
    {
        return $user->can(PermissionEnum::MUSHAF_REQUESTS_UPDATE->value);
    }

    public function delete(User $user, MushafRequest $mushafRequest): bool
    {
        return $user->can(PermissionEnum::MUSHAF_REQUESTS_DELETE->value);
    }

    public function approve(User $user, MushafRequest $mushafRequest): bool
    {
        return $user->can(PermissionEnum::MUSHAF_REQUESTS_APPROVE->value);
    }

    public function reject(User $user, MushafRequest $mushafRequest): bool
    {
        return $user->can(PermissionEnum::MUSHAF_REQUESTS_REJECT->value);
    }

    public function process(User $user, MushafRequest $mushafRequest): bool
    {
        return $user->can(PermissionEnum::MUSHAF_REQUESTS_PROCESS->value);
    }

    public function restore(User $user, MushafRequest $mushafRequest): bool
    {
        return false;
    }

    public function forceDelete(User $user, MushafRequest $mushafRequest): bool
    {
        return false;
    }
}
