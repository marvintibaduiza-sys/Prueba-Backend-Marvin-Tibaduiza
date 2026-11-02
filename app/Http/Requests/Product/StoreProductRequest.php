<?php

namespace App\Http\Requests\Product;

use App\Models\Currency;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    /**
     * The base currency is used when the client sends no currency (or null). If the base
     * currency does not exist, the field stays empty and fails "required" with a clear 422.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('currency_id') === null) {
            $this->merge(['currency_id' => Currency::base()?->id]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ProductRules::forStore();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ProductRules::messages();
    }
}
