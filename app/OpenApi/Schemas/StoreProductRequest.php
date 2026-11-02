<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

/**
 * Data to create a product. Amounts accept a number or a numeric string with up to 2 decimals.
 */
#[OA\Schema(
    schema: 'StoreProductRequest',
    required: ['name', 'description', 'price', 'tax_cost', 'manufacturing_cost'],
    properties: [
        new OA\Property(
            property: 'name',
            description: 'Letters (any language), spaces and hyphens only; digits are rejected.',
            type: 'string',
            maxLength: 150,
            pattern: '^[\p{L}\s\-]+$',
            example: 'Wireless Mouse',
        ),
        new OA\Property(property: 'description', type: 'string', maxLength: 1000, example: 'Ergonomic wireless mouse with USB receiver.'),
        new OA\Property(
            property: 'price',
            description: 'Base price in the product currency. Must be greater than 0, max 9999999999.99, up to 2 decimals.',
            type: 'number',
            maximum: 9999999999.99,
            minimum: 0.01,
            example: 19.90,
        ),
        new OA\Property(
            property: 'currency_id',
            description: 'ID of an existing currency. Optional: defaults to the base currency (USD).',
            type: 'integer',
            example: 1,
        ),
        new OA\Property(
            property: 'tax_cost',
            description: 'Tax cost in the product currency. 0 or greater, max 9999999999.99, up to 2 decimals.',
            type: 'number',
            maximum: 9999999999.99,
            minimum: 0,
            example: 3.78,
        ),
        new OA\Property(
            property: 'manufacturing_cost',
            description: 'Manufacturing cost in the product currency. 0 or greater, max 9999999999.99, up to 2 decimals.',
            type: 'number',
            maximum: 9999999999.99,
            minimum: 0,
            example: 7.50,
        ),
    ],
)]
class StoreProductRequest {}
