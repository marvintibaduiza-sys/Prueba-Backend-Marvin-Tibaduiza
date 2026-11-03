<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Role',
    required: ['id', 'name', 'permissions', 'users_count', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 2),
        new OA\Property(property: 'name', type: 'string', example: 'seller'),
        new OA\Property(
            property: 'permissions',
            description: 'Names of the permissions granted by the role, sorted by name.',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['currencies.view', 'prices.create', 'prices.view', 'products.create', 'products.update', 'products.view'],
        ),
        new OA\Property(property: 'users_count', description: 'Number of users holding the role.', type: 'integer', example: 3),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-01-15T10:30:00+00:00'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-01-15T10:30:00+00:00'),
    ],
)]
class Role {}
