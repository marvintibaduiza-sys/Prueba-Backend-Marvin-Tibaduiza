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
use OpenApi\Attributes as OA;
use Throwable;

class ProductPriceController extends Controller
{
    #[OA\Get(
        path: '/products/{product}/prices',
        operationId: 'products.prices.index',
        description: 'Requires `prices.view`. Paginated list, ordered by id, of the prices of the product in currencies other than its base currency.',
        summary: 'List the prices of a product',
        security: [['sanctum' => []]],
        tags: ['Product prices'],
        parameters: [
            new OA\Parameter(name: 'product', description: 'Product ID.', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'A page of prices.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ProductPrice')),
                    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                ]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function index(Request $request, Product $product): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ProductPrice::class);

        return ProductPriceResource::collection(
            $product->prices()->with('currency')->orderBy('id')->paginate($this->perPage($request))
        );
    }

    #[OA\Post(
        path: '/products/{product}/prices',
        operationId: 'products.prices.store',
        description: 'Requires `prices.create`. Registers the price of the product in another currency: the currency must differ from the product base currency (422) and the product can have only one price per currency (422: "The product already has a price in this currency."). Returns 409 with that same message when a concurrent request registered the same currency first.',
        summary: 'Register a price of a product',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreProductPriceRequest')),
        tags: ['Product prices'],
        parameters: [new OA\Parameter(name: 'product', description: 'Product ID.', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Price registered.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/ProductPrice')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
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
