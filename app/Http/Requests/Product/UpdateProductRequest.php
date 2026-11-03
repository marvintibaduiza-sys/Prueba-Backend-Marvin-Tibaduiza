<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public const PRICED_CURRENCY_MESSAGE = 'The product already has a price in this currency.';

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->product());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = array_map(
            fn (array $fieldRules) => ['sometimes', ...$fieldRules],
            ProductRules::forStore()
        );

        // A product cannot have a price registered in its own base currency.
        $rules['currency_id'][] = Rule::notIn($this->product()->prices()->pluck('currency_id'));

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...ProductRules::messages(),
            'currency_id.not_in' => self::PRICED_CURRENCY_MESSAGE,
        ];
    }

    private function product(): Product
    {
        return $this->route('product');
    }
}
