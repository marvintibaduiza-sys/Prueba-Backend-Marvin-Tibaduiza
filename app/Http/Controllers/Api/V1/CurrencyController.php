<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Currency\StoreCurrencyRequest;
use App\Http\Requests\Currency\UpdateCurrencyRequest;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use Throwable;

class CurrencyController extends Controller
{
    #[OA\Get(
        path: '/currencies',
        operationId: 'currencies.index',
        description: 'Requires `currencies.view`. Paginated list ordered by name.',
        summary: 'List currencies',
        security: [['sanctum' => []]],
        tags: ['Currencies'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'A page of currencies.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Currency')),
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
        Gate::authorize('viewAny', Currency::class);

        return CurrencyResource::collection(
            Currency::orderBy('name')->paginate($this->perPage($request))
        );
    }

    #[OA\Post(
        path: '/currencies',
        operationId: 'currencies.store',
        description: 'Requires `currencies.create`. `exchange_rate` is the number of units of the new currency per 1 USD. The name is the unique identifier of the currency (e.g. GBP).',
        summary: 'Create a currency',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreCurrencyRequest')),
        tags: ['Currencies'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Currency created.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Currency')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function store(StoreCurrencyRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $currency = Currency::create($request->validated());

            DB::commit();
        } catch (UniqueConstraintViolationException) {
            // A concurrent request stored the same unique value after validation passed.
            DB::rollBack();

            throw new ConflictException('A currency with this name already exists.');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->serverError($e);
        }

        return (new CurrencyResource($currency))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/currencies/{currency}',
        operationId: 'currencies.show',
        description: 'Requires `currencies.view`.',
        summary: 'Show a currency',
        security: [['sanctum' => []]],
        tags: ['Currencies'],
        parameters: [new OA\Parameter(name: 'currency', description: 'Currency ID.', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The currency.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Currency')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Currency $currency): CurrencyResource
    {
        Gate::authorize('view', $currency);

        return new CurrencyResource($currency);
    }

    #[OA\Put(
        path: '/currencies/{currency}',
        operationId: 'currencies.update',
        description: 'Requires `currencies.update`. Partial update: only the fields sent are validated and changed (PATCH is also accepted). For the base currency (USD) the name cannot be changed and the exchange rate must stay 1 (422).',
        summary: 'Update a currency',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateCurrencyRequest')),
        tags: ['Currencies'],
        parameters: [new OA\Parameter(name: 'currency', description: 'Currency ID.', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Currency updated.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Currency')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function update(UpdateCurrencyRequest $request, Currency $currency): CurrencyResource|JsonResponse
    {
        try {
            DB::beginTransaction();

            $currency->update($request->validated());

            DB::commit();
        } catch (UniqueConstraintViolationException) {
            // A concurrent request stored the same unique value after validation passed.
            DB::rollBack();

            throw new ConflictException('A currency with this name already exists.');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->serverError($e);
        }

        return new CurrencyResource($currency);
    }

    #[OA\Delete(
        path: '/currencies/{currency}',
        operationId: 'currencies.destroy',
        description: 'Requires `currencies.delete`. Returns 409 "The base currency cannot be deleted." for USD, and 409 "The currency is in use and cannot be deleted." when a product uses it as base currency or has a price in it.',
        summary: 'Delete a currency',
        security: [['sanctum' => []]],
        tags: ['Currencies'],
        parameters: [new OA\Parameter(name: 'currency', description: 'Currency ID.', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Currency deleted.'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function destroy(Currency $currency): Response|JsonResponse
    {
        Gate::authorize('delete', $currency);

        if ($currency->isBase()) {
            throw new ConflictException('The base currency cannot be deleted.');
        }

        if ($currency->isInUse()) {
            throw new ConflictException('The currency is in use and cannot be deleted.');
        }

        try {
            DB::beginTransaction();

            $currency->delete();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->serverError($e);
        }

        return response()->noContent();
    }
}
