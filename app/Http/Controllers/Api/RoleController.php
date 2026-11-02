<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Exceptions\ConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role;
use Throwable;

class RoleController extends Controller
{
    private const PROTECTED_ROLE_MESSAGE = 'The admin role cannot be modified or deleted.';

    #[OA\Get(
        path: '/roles',
        operationId: 'roles.index',
        description: 'Requires `roles.view`. Paginated list ordered by id; each role includes its permissions and the number of users holding it.',
        summary: 'List roles',
        security: [['sanctum' => []]],
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'A page of roles.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Role')),
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
        Gate::authorize('viewAny', Role::class);

        return RoleResource::collection(
            Role::query()
                // Eloquent subquery instead of withCount('users'): spatie resolves that relation from
                // the request guard ("sanctum"), which has no user provider, so it cannot build it here.
                ->addSelect(['users_count' => RoleAssignment::forUsers()
                    ->selectRaw('count(*)')
                    ->whereColumn('role_id', 'roles.id'),
                ])
                ->with('permissions')
                ->orderBy('id')
                ->paginate($this->perPage($request))
        );
    }

    #[OA\Post(
        path: '/roles',
        operationId: 'roles.store',
        description: 'Requires `roles.create`. Delegation rule: you can only grant permissions you hold (403 "You cannot grant permissions you do not hold.").',
        summary: 'Create a role',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreRoleRequest')),
        tags: ['Roles'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Role created.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Role')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function store(StoreRoleRequest $request): JsonResponse
    {
        Gate::authorize('grantPermissions', [Role::class, $request->validated('permissions')]);

        try {
            DB::beginTransaction();

            // Roles belong to the guard of the User model ("web"), not to the request guard ("sanctum").
            $role = Role::create([
                'name' => $request->validated('name'),
                'guard_name' => Guard::getDefaultName(User::class),
            ]);
            $role->syncPermissions($request->validated('permissions'));

            DB::commit();
        } catch (UniqueConstraintViolationException) {
            // A concurrent request stored the same unique value after validation passed.
            DB::rollBack();

            throw new ConflictException('A role with this name already exists.');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->serverError($e);
        }

        return (new RoleResource($role->load('permissions')->loadCount('users')))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/roles/{role}',
        operationId: 'roles.show',
        description: 'Requires `roles.view`.',
        summary: 'Show a role',
        security: [['sanctum' => []]],
        tags: ['Roles'],
        parameters: [new OA\Parameter(name: 'role', description: 'Role ID.', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The role with its permissions and users count.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Role')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Role $role): RoleResource
    {
        Gate::authorize('view', $role);

        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    #[OA\Put(
        path: '/roles/{role}',
        operationId: 'roles.update',
        description: <<<'TEXT'
            Requires `roles.update`. Partial update: only the fields sent are validated and changed (PATCH is also accepted); `permissions` replaces all the permissions of the role.

            Delegation rule (403): "You cannot grant permissions you do not hold."

            Conflict (409): "The admin role cannot be modified or deleted."
            TEXT,
        summary: 'Update a role',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateRoleRequest')),
        tags: ['Roles'],
        parameters: [new OA\Parameter(name: 'role', description: 'Role ID.', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role updated.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Role')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function update(UpdateRoleRequest $request, Role $role): RoleResource|JsonResponse
    {
        $this->ensureIsNotProtected($role);

        if ($request->has('permissions')) {
            Gate::authorize('grantPermissions', [Role::class, $request->validated('permissions')]);
        }

        try {
            DB::beginTransaction();

            if ($request->has('name')) {
                $role->update(['name' => $request->validated('name')]);
            }

            if ($request->has('permissions')) {
                $role->syncPermissions($request->validated('permissions'));
            }

            DB::commit();
        } catch (UniqueConstraintViolationException) {
            // A concurrent request stored the same unique value after validation passed.
            DB::rollBack();

            throw new ConflictException('A role with this name already exists.');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->serverError($e);
        }

        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    #[OA\Delete(
        path: '/roles/{role}',
        operationId: 'roles.destroy',
        description: 'Requires `roles.delete`. Conflicts (409): "The admin role cannot be modified or deleted." and "The role is assigned to users and cannot be deleted."',
        summary: 'Delete a role',
        security: [['sanctum' => []]],
        tags: ['Roles'],
        parameters: [new OA\Parameter(name: 'role', description: 'Role ID.', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Role deleted.'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function destroy(Role $role): Response|JsonResponse
    {
        Gate::authorize('delete', $role);

        $this->ensureIsNotProtected($role);

        try {
            DB::beginTransaction();

            // Locked read: a concurrent assignment of this role waits until we finish.
            $isAssigned = RoleAssignment::ofRole($role)->lockForUpdate()->exists();

            if ($isAssigned) {
                throw new ConflictException('The role is assigned to users and cannot be deleted.');
            }

            $role->delete();

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

    private function ensureIsNotProtected(Role $role): void
    {
        if ($role->name === RoleName::Admin->value) {
            throw new ConflictException(self::PROTECTED_ROLE_MESSAGE);
        }
    }
}
