<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

/**
 * A currency and its exchange rate against the base currency (USD).
 */
#[OA\Schema(
    schema: 'Currency',
    required: ['id', 'name', 'symbol', 'exchange_rate', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 2),
        new OA\Property(property: 'name', description: 'Unique identifier of the currency.', type: 'string', example: 'EUR'),
        new OA\Property(property: 'symbol', type: 'string', example: '€'),
        new OA\Property(
            property: 'exchange_rate',
            description: 'Units of this currency per 1 USD (USD is the base currency, rate 1). Example: EUR 0.89 means 1 USD = 0.89 EUR. Returned as a string with 6 decimals.',
            type: 'string',
            example: '0.890000',
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-01-15T10:30:00+00:00'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-01-15T10:30:00+00:00'),
    ],
)]
class Currency {}
