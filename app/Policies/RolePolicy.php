<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::RolesView->value);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermissionTo(PermissionName::RolesView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::RolesCreate->value);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermissionTo(PermissionName::RolesUpdate->value);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo(PermissionName::RolesDelete->value);
    }
}
