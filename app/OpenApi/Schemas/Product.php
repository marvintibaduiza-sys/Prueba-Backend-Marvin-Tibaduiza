<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

/**
 * A product with its base price, costs and currency.
 */
#[OA\Schema(
    schema: 'Product',
    required: ['id', 'name', 'description', 'price', 'tax_cost', 'manufacturing_cost', 'currency', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Wireless Mouse'),
        new OA\Property(property: 'description', type: 'string', example: 'Ergonomic wireless mouse with USB receiver.'),
        new OA\Property(property: 'price', description: 'Base price in the product currency, 2 decimals.', type: 'string', example: '19.90'),
        new OA\Property(property: 'tax_cost', description: 'Tax cost in the product currency, 2 decimals.', type: 'string', example: '3.78'),
        new OA\Property(property: 'manufacturing_cost', description: 'Manufacturing cost in the product currency, 2 decimals.', type: 'string', example: '7.50'),
        new OA\Property(property: 'currency', ref: '#/components/schemas/Currency'),
        new OA\Property(
            property: 'prices',
            description: 'Prices in other currencies. Present only in the show and update responses.',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/ProductPrice'),
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-01-15T10:30:00+00:00'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-01-15T10:30:00+00:00'),
    ],
)]
class Product {}
