<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Auth\Access\Response;
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

    public function update(User $user, Role $role): Response|bool
    {
        if (! $user->hasPermissionTo(PermissionName::RolesUpdate->value)) {
            return false;
        }

        // You can't edit a role with permissions you don't have.
        $notHeld = $role->permissions->pluck('name')->diff($user->getAllPermissions()->pluck('name'));

        return $notHeld->isEmpty()
            ? Response::allow()
            : Response::deny('You cannot manage a role with permissions you do not hold.');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo(PermissionName::RolesDelete->value);
    }

    /**
     * You can only give permissions you already have.
     *
     * @param  list<string>  $permissions
     */
    public function grantPermissions(User $user, array $permissions): Response
    {
        $notHeld = collect($permissions)->diff($user->getAllPermissions()->pluck('name'));

        return $notHeld->isEmpty()
            ? Response::allow()
            : Response::deny('You cannot grant permissions you do not hold.');
    }
}
