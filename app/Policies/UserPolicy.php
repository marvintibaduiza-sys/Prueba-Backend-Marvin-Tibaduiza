<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::UsersView->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo(PermissionName::UsersView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::UsersCreate->value);
    }

    public function update(User $user, User $model): Response|bool
    {
        if (! $user->hasPermissionTo(PermissionName::UsersUpdate->value)) {
            return false;
        }

        return $this->canManage($user, $model);
    }

    public function delete(User $user, User $model): Response|bool
    {
        if (! $user->hasPermissionTo(PermissionName::UsersDelete->value)) {
            return false;
        }

        return $this->canManage($user, $model);
    }

    /**
     * You can't edit or delete a user with more permissions than you.
     */
    private function canManage(User $user, User $model): Response
    {
        $notHeld = $model->getAllPermissions()->pluck('name')->diff($user->getAllPermissions()->pluck('name'));

        return $notHeld->isEmpty()
            ? Response::allow()
            : Response::deny('You cannot manage a user with permissions you do not hold.');
    }

    /**
     * You can only assign a role if you have all its permissions.
     */
    public function assignRole(User $user, string $roleName): Response
    {
        $rolePermissions = Role::findByName($roleName, Guard::getDefaultName(User::class))->permissions->pluck('name');
        $notHeld = $rolePermissions->diff($user->getAllPermissions()->pluck('name'));

        return $notHeld->isEmpty()
            ? Response::allow()
            : Response::deny('You cannot assign a role with permissions you do not hold.');
    }
}
