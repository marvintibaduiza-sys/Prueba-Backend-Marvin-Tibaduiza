<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Currency;
use App\Models\User;

class CurrencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::CurrenciesView->value);
    }

    public function view(User $user, Currency $currency): bool
    {
        return $user->hasPermissionTo(PermissionName::CurrenciesView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::CurrenciesCreate->value);
    }

    public function update(User $user, Currency $currency): bool
    {
        return $user->hasPermissionTo(PermissionName::CurrenciesUpdate->value);
    }

    public function delete(User $user, Currency $currency): bool
    {
        return $user->hasPermissionTo(PermissionName::CurrenciesDelete->value);
    }
}
