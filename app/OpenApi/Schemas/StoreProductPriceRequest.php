<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreProductPriceRequest',
    required: ['currency_id', 'price'],
    properties: [
        new OA\Property(
            property: 'currency_id',
            description: 'ID of an existing currency. Must differ from the product base currency, and the product must not have a price in it yet.',
            type: 'integer',
            example: 2,
        ),
        new OA\Property(
            property: 'price',
            description: 'Price in that currency. Must be greater than 0, max 9999999999.99, up to 2 decimals.',
            type: 'number',
            maximum: 9999999999.99,
            minimum: 0.01,
            example: 17.70,
        ),
    ],
)]
class StoreProductPriceRequest {}
