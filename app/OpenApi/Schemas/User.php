<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

/**
 * A user with its roles and effective permissions.
 */
#[OA\Schema(
    schema: 'User',
    required: ['id', 'name', 'email', 'roles', 'permissions', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Jane Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
        new OA\Property(
            property: 'roles',
            description: 'Names of the roles assigned to the user.',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['seller'],
        ),
        new OA\Property(
            property: 'permissions',
            description: 'Effective permissions granted through the roles, sorted by name.',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['currencies.view', 'prices.create', 'prices.view', 'products.create', 'products.update', 'products.view'],
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-01-15T10:30:00+00:00'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-01-15T10:30:00+00:00'),
    ],
)]
class User {}
