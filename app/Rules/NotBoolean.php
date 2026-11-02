<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The "integer" rule accepts JSON true as 1. Ids must be real numbers, never booleans.
 */
class NotBoolean implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_bool($value)) {
            $fail('The :attribute field must be an integer.');
        }
    }
}
