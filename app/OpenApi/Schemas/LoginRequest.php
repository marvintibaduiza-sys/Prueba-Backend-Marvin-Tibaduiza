<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

/**
 * Credentials exchanged for a token.
 */
#[OA\Schema(
    schema: 'LoginRequest',
    required: ['email', 'password'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'admin@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
    ],
)]
class LoginRequest {}
