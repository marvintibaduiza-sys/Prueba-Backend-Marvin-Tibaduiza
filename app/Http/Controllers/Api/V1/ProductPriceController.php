<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductPrice\StoreProductPriceRequest;
use App\Http\Resources\ProductPriceResource;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ProductPriceController extends Controller
{
    public function index(Request $request, Product $product): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ProductPrice::class);

        return ProductPriceResource::collection(
            $product->prices()->with('currency')->orderBy('id')->paginate($this->perPage($request))
        );
    }

    public function store(StoreProductPriceRequest $request, Product $product): JsonResponse
    {
        try {
            DB::beginTransaction();

            $price = $product->prices()->create($request->validated());

            DB::commit();
        } catch (UniqueConstraintViolationException) {
            // Another request registered the same currency after validation passed.
            DB::rollBack();

            throw new ConflictException(StoreProductPriceRequest::DUPLICATE_MESSAGE);
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->serverError($e);
        }

        return (new ProductPriceResource($price->load('currency')))->response()->setStatusCode(201);
    }
}
