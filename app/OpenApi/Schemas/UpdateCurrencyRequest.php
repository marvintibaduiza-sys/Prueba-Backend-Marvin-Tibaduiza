<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

/**
 * Partial update of a currency: only the fields sent are validated and changed.
 */
#[OA\Schema(
    schema: 'UpdateCurrencyRequest',
    properties: [
        new OA\Property(property: 'name', description: 'Must be unique.', type: 'string', maxLength: 50, example: 'Euro'),
        new OA\Property(
            property: 'code',
            description: '3-letter uppercase ISO 4217 code. Must be unique. The base currency (USD) code cannot be changed.',
            type: 'string',
            pattern: '^[A-Z]{3}$',
            example: 'EUR',
        ),
        new OA\Property(property: 'symbol', type: 'string', maxLength: 5, example: '€'),
        new OA\Property(
            property: 'exchange_rate',
            description: 'Units of this currency per 1 USD. Must be greater than 0, max 999999.999999, up to 6 decimals. The base currency (USD) rate must stay 1.',
            type: 'number',
            maximum: 999999.999999,
            minimum: 0.000001,
            example: 0.92,
        ),
    ],
)]
class UpdateCurrencyRequest {}
