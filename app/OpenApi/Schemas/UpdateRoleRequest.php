<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateRoleRequest',
    properties: [
        new OA\Property(
            property: 'name',
            description: 'Lowercase; starts with a letter and may contain letters, numbers, dashes and underscores. Must be unique.',
            type: 'string',
            maxLength: 50,
            pattern: '^[a-z][a-z0-9_-]*$',
            example: 'senior-auditor',
        ),
        new OA\Property(
            property: 'permissions',
            description: 'Replaces all the permissions of the role. At least one distinct, existing permission name; you can only grant permissions you hold.',
            type: 'array',
            items: new OA\Items(type: 'string'),
            minItems: 1,
            uniqueItems: true,
            example: ['products.view', 'products.update', 'prices.view'],
        ),
    ],
)]
class UpdateRoleRequest {}
