<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Currency\StoreCurrencyRequest;
use App\Http\Requests\Currency\UpdateCurrencyRequest;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class CurrencyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Currency::class);

        return CurrencyResource::collection(
            Currency::orderBy('code')->paginate($this->perPage($request))
        );
    }

    public function store(StoreCurrencyRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $currency = Currency::create($request->validated());

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->serverError($e);
        }

        return (new CurrencyResource($currency))->response()->setStatusCode(201);
    }

    public function show(Currency $currency): CurrencyResource
    {
        Gate::authorize('view', $currency);

        return new CurrencyResource($currency);
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): CurrencyResource|JsonResponse
    {
        try {
            DB::beginTransaction();

            $currency->update($request->validated());

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->serverError($e);
        }

        return new CurrencyResource($currency);
    }

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
