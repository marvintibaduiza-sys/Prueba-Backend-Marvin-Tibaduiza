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
use OpenApi\Attributes as OA;
use Throwable;

class ProductController extends Controller
{
    #[OA\Get(
        path: '/products',
        operationId: 'products.index',
        description: 'Requires `products.view`. Paginated list ordered by id; each product includes its base currency (not the prices in other currencies).',
        summary: 'List products',
        security: [['sanctum' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'A page of products.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Product')),
                    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                ]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);

        return ProductResource::collection(
            Product::with('currency')->orderBy('id')->paginate($this->perPage($request))
        );
    }

    #[OA\Post(
        path: '/products',
        operationId: 'products.store',
        description: 'Requires `products.create`. The name accepts letters, spaces and hyphens only (digits are rejected). When `currency_id` is omitted the base currency (USD) is used.',
        summary: 'Create a product',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreProductRequest')),
        tags: ['Products'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Product created.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
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

    #[OA\Get(
        path: '/products/{product}',
        operationId: 'products.show',
        description: 'Requires `products.view`. Includes the base currency and the prices registered in other currencies.',
        summary: 'Show a product',
        security: [['sanctum' => []]],
        tags: ['Products'],
        parameters: [new OA\Parameter(name: 'product', description: 'Product ID.', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The product with its prices.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return new ProductResource($product->load(['currency', 'prices.currency']));
    }

    #[OA\Put(
        path: '/products/{product}',
        operationId: 'products.update',
        description: 'Requires `products.update`. Partial update: only the fields sent are validated and changed (PATCH is also accepted). `currency_id` cannot be a currency in which the product already has a price (422: "The product already has a price in this currency.").',
        summary: 'Update a product',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateProductRequest')),
        tags: ['Products'],
        parameters: [new OA\Parameter(name: 'product', description: 'Product ID.', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Product updated, with its prices.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
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

    #[OA\Delete(
        path: '/products/{product}',
        operationId: 'products.destroy',
        description: 'Requires `products.delete`. The prices of the product in other currencies are deleted with it.',
        summary: 'Delete a product',
        security: [['sanctum' => []]],
        tags: ['Products'],
        parameters: [new OA\Parameter(name: 'product', description: 'Product ID.', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Product deleted.'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
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
