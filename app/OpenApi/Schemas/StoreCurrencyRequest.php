<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreCurrencyRequest',
    required: ['name', 'symbol', 'exchange_rate'],
    properties: [
        new OA\Property(property: 'name', description: 'Unique identifier of the currency.', type: 'string', maxLength: 50, example: 'GBP'),
        new OA\Property(property: 'symbol', type: 'string', maxLength: 5, example: '£'),
        new OA\Property(
            property: 'exchange_rate',
            description: 'Units of this currency per 1 USD. Must be greater than 0, max 999999.999999, up to 6 decimals.',
            type: 'number',
            maximum: 999999.999999,
            minimum: 0.000001,
            example: 0.79,
        ),
    ],
)]
class StoreCurrencyRequest {}
