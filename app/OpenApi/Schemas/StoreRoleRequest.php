<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

/**
 * Data to create a role with its permissions.
 */
#[OA\Schema(
    schema: 'StoreRoleRequest',
    required: ['name', 'permissions'],
    properties: [
        new OA\Property(
            property: 'name',
            description: 'Lowercase; starts with a letter and may contain letters, numbers, dashes and underscores. Must be unique.',
            type: 'string',
            maxLength: 50,
            pattern: '^[a-z][a-z0-9_-]*$',
            example: 'auditor',
        ),
        new OA\Property(
            property: 'permissions',
            description: 'At least one distinct, existing permission name. You can only grant permissions you hold.',
            type: 'array',
            items: new OA\Items(type: 'string'),
            minItems: 1,
            uniqueItems: true,
            example: ['products.view', 'prices.view', 'currencies.view'],
        ),
    ],
)]
class StoreRoleRequest {}
