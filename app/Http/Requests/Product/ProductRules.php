<?php

namespace App\Http\Requests\Product;

use App\Rules\NotBoolean;

final class ProductRules
{
    /**
     * Biggest value a decimal(12,2) column can store.
     */
    public const MAX_AMOUNT = '9999999999.99';

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function forStore(): array
    {
        $amount = ['required', 'numeric', 'max:'.self::MAX_AMOUNT, 'decimal:0,2'];

        return [
            // Letters (accents too), spaces and hyphens. No digits.
            'name' => ['required', 'string', 'max:150', 'regex:/^(?=.*\pL)[\pL\pM \-]+$/u'],
            'description' => ['required', 'string', 'max:1000'],
            'price' => [...$amount, 'gt:0'],
            'currency_id' => ['required', 'integer', new NotBoolean, 'exists:currencies,id'],
            'tax_cost' => [...$amount, 'min:0'],
            'manufacturing_cost' => [...$amount, 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.regex' => 'The name may only contain letters, spaces and hyphens, and must include a letter.',
        ];
    }
}
