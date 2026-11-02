<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

/**
 * Data to create a currency.
 */
#[OA\Schema(
    schema: 'StoreCurrencyRequest',
    required: ['name', 'code', 'symbol', 'exchange_rate'],
    properties: [
        new OA\Property(property: 'name', description: 'Must be unique.', type: 'string', maxLength: 50, example: 'British Pound'),
        new OA\Property(
            property: 'code',
            description: '3-letter uppercase ISO 4217 code. Must be unique.',
            type: 'string',
            pattern: '^[A-Z]{3}$',
            example: 'GBP',
        ),
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
