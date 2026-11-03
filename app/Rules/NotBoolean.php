<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * "integer" accepts true as 1, but an id can't be a boolean.
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
