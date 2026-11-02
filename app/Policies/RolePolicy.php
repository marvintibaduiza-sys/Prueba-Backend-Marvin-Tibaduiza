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

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermissionTo(PermissionName::RolesUpdate->value);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo(PermissionName::RolesDelete->value);
    }

    /**
     * Delegation rule: users may only grant permissions they already hold, so a role manager
     * can never escalate their own (or anyone's) privileges beyond their own.
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
