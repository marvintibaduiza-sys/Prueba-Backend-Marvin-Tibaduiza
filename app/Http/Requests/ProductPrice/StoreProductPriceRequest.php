<?php

namespace App\Http\Requests\ProductPrice;

use App\Http\Requests\Product\ProductRules;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductPriceRequest extends FormRequest
{
    public const DUPLICATE_MESSAGE = 'The product already has a price in this currency.';

    public function authorize(): bool
    {
        return $this->user()->can('create', ProductPrice::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $product = $this->product();

        return [
            'currency_id' => [
                'required',
                'integer',
                'exists:currencies,id',
                // The base currency price already lives in products.price.
                Rule::notIn([$product->currency_id]),
                // One price per currency per product.
                Rule::unique('product_prices', 'currency_id')->where('product_id', $product->id),
            ],
            'price' => ['required', 'numeric', 'gt:0', 'max:'.ProductRules::MAX_AMOUNT, 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'currency_id.not_in' => 'The price currency must differ from the product base currency.',
            'currency_id.unique' => self::DUPLICATE_MESSAGE,
        ];
    }

    private function product(): Product
    {
        return $this->route('product');
    }
}
