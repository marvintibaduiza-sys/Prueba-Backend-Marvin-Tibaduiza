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
            'name' => ['required', 'string', 'max:50', 'unique:currencies,name'],
            'code' => ['required', 'string', 'regex:/^[A-Z]{3}$/', 'unique:currencies,code'],
            'symbol' => ['required', 'string', 'max:5'],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:999999.999999', 'decimal:0,6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'The code must be a 3-letter uppercase ISO 4217 code (e.g. USD).',
        ];
    }
}
