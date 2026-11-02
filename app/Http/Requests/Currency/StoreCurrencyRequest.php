<?php

namespace App\Http\Requests\Currency;

use App\Models\Currency;
use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Currency::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // The name is the unique identifier of the currency (e.g. USD, EUR).
            'name' => ['required', 'string', 'max:50', 'unique:currencies,name'],
            'symbol' => ['required', 'string', 'max:5'],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:999999.999999', 'decimal:0,6'],
        ];
    }
}
