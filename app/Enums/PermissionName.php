<?php

namespace App\Enums;

/**
 * Single source of truth for every permission the API checks.
 * Permissions are fixed: they are seeded, never created through the API.
 */
enum PermissionName: string
{
    case ProductsView = 'products.view';
    case ProductsCreate = 'products.create';
    case ProductsUpdate = 'products.update';
    case ProductsDelete = 'products.delete';

    case PricesView = 'prices.view';
    case PricesCreate = 'prices.create';

    case CurrenciesView = 'currencies.view';
    case CurrenciesCreate = 'currencies.create';
    case CurrenciesUpdate = 'currencies.update';
    case CurrenciesDelete = 'currencies.delete';

    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';

    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';

    /**
     * Default permissions granted to each seeded role.
     *
     * @return list<self>
     */
    public static function forRole(RoleName $role): array
    {
        return match ($role) {
            RoleName::Admin => self::cases(),
            RoleName::Seller => [
                self::ProductsView,
                self::ProductsCreate,
                self::ProductsUpdate,
                self::PricesView,
                self::PricesCreate,
                self::CurrenciesView,
            ],
        };
    }
}
