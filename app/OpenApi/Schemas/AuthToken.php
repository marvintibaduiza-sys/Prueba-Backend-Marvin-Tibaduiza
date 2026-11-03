<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AuthToken',
    required: ['token', 'token_type', 'expires_at', 'user'],
    properties: [
        new OA\Property(
            property: 'token',
            description: 'Send it as `Authorization: Bearer <token>`.',
            type: 'string',
            example: '1|kD8fJ2mQx7pL0vN4tR6yB3cH9wZ5sA1eG8uI2oP4',
        ),
        new OA\Property(property: 'token_type', type: 'string', enum: ['Bearer'], example: 'Bearer'),
        new OA\Property(
            property: 'expires_at',
            description: 'Moment the token stops being valid (60 minutes after login).',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: '2026-01-15T11:30:00+00:00',
        ),
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
    ],
)]
class AuthToken {}
