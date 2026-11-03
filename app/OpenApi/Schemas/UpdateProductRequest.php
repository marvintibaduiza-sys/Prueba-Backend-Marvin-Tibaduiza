<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateProductRequest',
    properties: [
        new OA\Property(
            property: 'name',
            description: 'Letters (any language, accents included), spaces and hyphens only, with at least one letter; digits, symbols, tabs and line breaks are rejected.',
            type: 'string',
            maxLength: 150,
            pattern: '^(?=.*\p{L})[\p{L}\p{M} \-]+$',
            example: 'Wireless Mouse Pro',
        ),
        new OA\Property(property: 'description', type: 'string', maxLength: 1000, example: 'Ergonomic wireless mouse with USB-C receiver.'),
        new OA\Property(
            property: 'price',
            description: 'Base price in the product currency. Must be greater than 0, max 9999999999.99, up to 2 decimals.',
            type: 'number',
            maximum: 9999999999.99,
            minimum: 0.01,
            example: 24.90,
        ),
        new OA\Property(
            property: 'currency_id',
            description: 'ID of an existing currency. Must not be a currency in which the product already has a price.',
            type: 'integer',
            example: 1,
        ),
        new OA\Property(
            property: 'tax_cost',
            description: 'Tax cost in the product currency. 0 or greater, max 9999999999.99, up to 2 decimals.',
            type: 'number',
            maximum: 9999999999.99,
            minimum: 0,
            example: 4.73,
        ),
        new OA\Property(
            property: 'manufacturing_cost',
            description: 'Manufacturing cost in the product currency. 0 or greater, max 9999999999.99, up to 2 decimals.',
            type: 'number',
            maximum: 9999999999.99,
            minimum: 0,
            example: 8.10,
        ),
    ],
)]
class UpdateProductRequest {}
