<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreUserRequest',
    required: ['name', 'email', 'password', 'password_confirmation', 'role'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'John Smith'),
        new OA\Property(property: 'email', description: 'Must be unique.', type: 'string', format: 'email', maxLength: 150, example: 'john@example.com'),
        new OA\Property(property: 'password', description: 'At least 8 characters.', type: 'string', format: 'password', minLength: 8, example: 'secret-pass-123'),
        new OA\Property(property: 'password_confirmation', description: 'Must match `password`.', type: 'string', format: 'password', example: 'secret-pass-123'),
        new OA\Property(
            property: 'role',
            description: 'Name of an existing role. You can only assign a role whose permissions you all hold.',
            type: 'string',
            example: 'seller',
        ),
    ],
)]
class StoreUserRequest {}
