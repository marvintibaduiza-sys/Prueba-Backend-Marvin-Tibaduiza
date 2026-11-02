<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

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

    public function update(User $user, User $model): bool
    {
        return $user->hasPermissionTo(PermissionName::UsersUpdate->value);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPermissionTo(PermissionName::UsersDelete->value);
    }
}
