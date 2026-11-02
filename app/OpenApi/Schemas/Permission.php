<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

/**
 * A permission from the fixed, seeded catalog.
 */
#[OA\Schema(
    schema: 'Permission',
    required: ['id', 'name'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'products.view'),
    ],
)]
class Permission {}
