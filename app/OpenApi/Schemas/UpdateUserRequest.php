<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateUserRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'John A. Smith'),
        new OA\Property(property: 'email', description: 'Must be unique.', type: 'string', format: 'email', maxLength: 150, example: 'john.smith@example.com'),
        new OA\Property(
            property: 'password',
            description: 'At least 8 characters; requires `password_confirmation`. Changing it revokes the user\'s other sessions.',
            type: 'string',
            format: 'password',
            minLength: 8,
            example: 'new-secret-456',
        ),
        new OA\Property(property: 'password_confirmation', description: 'Must match `password`.', type: 'string', format: 'password', example: 'new-secret-456'),
        new OA\Property(
            property: 'role',
            description: 'Name of an existing role; replaces the current role. You can only assign a role whose permissions you all hold.',
            type: 'string',
            example: 'admin',
        ),
    ],
)]
class UpdateUserRequest {}
