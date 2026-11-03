<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Error',
    required: ['message'],
    properties: [new OA\Property(property: 'message', type: 'string', example: 'Resource not found.')],
)]
class Error {}
