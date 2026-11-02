<?php

namespace App\Http\Requests\Currency;

use App\Models\Currency;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->currency());
    }

    /**
     * Partial updates are allowed: only the fields sent are validated.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $currency = $this->currency();

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:50', Rule::unique('currencies', 'name')->ignore($currency),
                // The base currency is identified by its name, so renaming it would lose the reference.
                function (string $attribute, mixed $value, Closure $fail) use ($currency) {
                    if ($currency->isBase() && $value !== $currency->name) {
                        $fail('The base currency name cannot be changed.');
                    }
                },
            ],
            'symbol' => ['sometimes', 'required', 'string', 'max:5'],
            'exchange_rate' => [
                'sometimes', 'required', 'numeric', 'gt:0', 'max:999999.999999', 'decimal:0,6',
                function (string $attribute, mixed $value, Closure $fail) use ($currency) {
                    if ($currency->isBase() && is_numeric($value) && (float) $value !== 1.0) {
                        $fail('The base currency exchange rate must be 1.');
                    }
                },
            ],
        ];
    }

    private function currency(): Currency
    {
        return $this->route('currency');
    }
}
