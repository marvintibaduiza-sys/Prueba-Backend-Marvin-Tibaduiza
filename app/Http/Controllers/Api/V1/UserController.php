<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Exceptions\ConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use OpenApi\Attributes as OA;
use Throwable;

class UserController extends Controller
{
    private const LAST_ADMIN_MESSAGE = 'The last administrator cannot be removed or demoted.';

    #[OA\Get(
        path: '/users',
        operationId: 'users.index',
        description: 'Requires `users.view`. Paginated list ordered by id; each user includes roles and effective permissions.',
        summary: 'List users',
        security: [['sanctum' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'A page of users.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/User')),
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
        Gate::authorize('viewAny', User::class);

        return UserResource::collection(
            User::with('roles.permissions')->orderBy('id')->paginate($this->perPage($request))
        );
    }

    #[OA\Post(
        path: '/users',
        operationId: 'users.store',
        description: 'Requires `users.create`. Delegation rule: you can only assign a role whose permissions you all hold (403 "You cannot assign a role with permissions you do not hold.").',
        summary: 'Create a user',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreUserRequest')),
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'User created.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function store(StoreUserRequest $request): JsonResponse
    {
        Gate::authorize('assignRole', [User::class, $request->validated('role')]);

        try {
            DB::beginTransaction();

            $user = User::create($request->safe()->except('role'));
            $user->assignRole($request->validated('role'));

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->serverError($e);
        }

        return (new UserResource($user->load('roles.permissions')))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/users/{user}',
        operationId: 'users.show',
        description: 'Requires `users.view`.',
        summary: 'Show a user',
        security: [['sanctum' => []]],
        tags: ['Users'],
        parameters: [new OA\Parameter(name: 'user', description: 'User ID.', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The user with roles and effective permissions.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load('roles.permissions'));
    }

    #[OA\Put(
        path: '/users/{user}',
        operationId: 'users.update',
        description: <<<'TEXT'
            Requires `users.update`. Partial update: only the fields sent are validated and changed (PATCH is also accepted).

            Delegation rules (403): "You cannot manage a user with permissions you do not hold." and "You cannot assign a role with permissions you do not hold."

            Conflicts (409): "You cannot change your own role." and "The last administrator cannot be removed or demoted."

            Changing the password revokes every session of the user, except the current one when you change your own password.
            TEXT,
        summary: 'Update a user',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateUserRequest')),
        tags: ['Users'],
        parameters: [new OA\Parameter(name: 'user', description: 'User ID.', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function update(UpdateUserRequest $request, User $user): UserResource|JsonResponse
    {
        $newRole = $request->validated('role');
        $changesRole = $newRole !== null && ! $user->hasRole($newRole);

        if ($changesRole) {
            Gate::authorize('assignRole', [User::class, $newRole]);
        }

        if ($changesRole && $user->is($request->user())) {
            throw new ConflictException('You cannot change your own role.');
        }

        try {
            DB::beginTransaction();

            if ($changesRole && $newRole !== RoleName::Admin->value) {
                $this->ensureIsNotLastAdmin($user);
            }

            $user->update($request->safe()->except('role'));

            if ($newRole !== null) {
                $user->syncRoles([$newRole]);
            }

            if ($request->validated('password') !== null) {
                $this->revokeOtherSessions($request, $user);
            }

            DB::commit();
        } catch (ConflictException $e) {
            DB::rollBack();

            throw $e;
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->serverError($e);
        }

        return new UserResource($user->load('roles.permissions'));
    }

    #[OA\Delete(
        path: '/users/{user}',
        operationId: 'users.destroy',
        description: <<<'TEXT'
            Requires `users.delete`. Every session of the deleted user is revoked.

            Delegation rule (403): "You cannot manage a user with permissions you do not hold."

            Conflicts (409): "You cannot delete your own account." and "The last administrator cannot be removed or demoted."
            TEXT,
        summary: 'Delete a user',
        security: [['sanctum' => []]],
        tags: ['Users'],
        parameters: [new OA\Parameter(name: 'user', description: 'User ID.', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'User deleted.'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function destroy(Request $request, User $user): Response|JsonResponse
    {
        Gate::authorize('delete', $user);

        if ($user->is($request->user())) {
            throw new ConflictException('You cannot delete your own account.');
        }

        try {
            DB::beginTransaction();

            $this->ensureIsNotLastAdmin($user);

            // Revoke every active session before removing the account.
            $user->tokens()->delete();
            $user->delete();

            DB::commit();
        } catch (ConflictException $e) {
            DB::rollBack();

            throw $e;
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->serverError($e);
        }

        return response()->noContent();
    }

    /**
     * Must run inside the transaction: the lock is held until commit or rollback.
     */
    private function ensureIsNotLastAdmin(User $user): void
    {
        if ($user->isLastAdminForUpdate()) {
            throw new ConflictException(self::LAST_ADMIN_MESSAGE);
        }
    }

    /**
     * After a password change, every session of that user is revoked, except the one
     * making the request when users change their own password.
     */
    private function revokeOtherSessions(Request $request, User $user): void
    {
        $currentToken = $request->user()->currentAccessToken();

        $user->tokens()
            ->when(
                $user->is($request->user()) && $currentToken instanceof PersonalAccessToken,
                fn ($query) => $query->whereKeyNot($currentToken->getKey())
            )
            ->delete();
    }
}
