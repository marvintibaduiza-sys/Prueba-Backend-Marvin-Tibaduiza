<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

/**
 * Partial update of a currency: only the fields sent are validated and changed.
 */
#[OA\Schema(
    schema: 'UpdateCurrencyRequest',
    properties: [
        new OA\Property(property: 'name', description: 'Unique identifier of the currency. The base currency (USD) name cannot be changed.', type: 'string', maxLength: 50, example: 'EUR'),
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
