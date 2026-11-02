<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class ProductPricePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::PricesView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::PricesCreate->value);
    }
}
