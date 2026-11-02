<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);

        return ProductResource::collection(
            Product::with('currency')->orderBy('id')->paginate($this->perPage($request))
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $product = Product::create($request->validated());

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->serverError($e);
        }

        return (new ProductResource($product->load('currency')))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return new ProductResource($product->load(['currency', 'prices.currency']));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource|JsonResponse
    {
        try {
            DB::beginTransaction();

            $product->update($request->validated());

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->serverError($e);
        }

        return new ProductResource($product->load(['currency', 'prices.currency']));
    }

    public function destroy(Product $product): Response|JsonResponse
    {
        Gate::authorize('delete', $product);

        try {
            DB::beginTransaction();

            // Its prices are removed by the database (cascade on delete).
            $product->delete();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->serverError($e);
        }

        return response()->noContent();
    }
}
