<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Exceptions\ConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role;
use Throwable;

class RoleController extends Controller
{
    private const PROTECTED_ROLE_MESSAGE = 'The admin role cannot be modified or deleted.';

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Role::class);

        // Explicit subquery instead of withCount('users'): spatie resolves that relation from the
        // request guard ("sanctum"), which has no user provider, so it cannot build it here.
        $usersCount = DB::table(config('permission.table_names.model_has_roles'))
            ->selectRaw('count(*)')
            ->whereColumn('role_id', 'roles.id')
            ->where('model_type', (new User)->getMorphClass());

        return RoleResource::collection(
            Role::query()
                ->select('roles.*')
                ->selectSub($usersCount, 'users_count')
                ->with('permissions')
                ->orderBy('id')
                ->paginate($this->perPage($request))
        );
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Roles belong to the guard of the User model ("web"), not to the request guard ("sanctum").
            $role = Role::create([
                'name' => $request->validated('name'),
                'guard_name' => Guard::getDefaultName(User::class),
            ]);
            $role->syncPermissions($request->validated('permissions'));

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->serverError($e);
        }

        return (new RoleResource($role->load('permissions')->loadCount('users')))->response()->setStatusCode(201);
    }

    public function show(Role $role): RoleResource
    {
        Gate::authorize('view', $role);

        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource|JsonResponse
    {
        $this->ensureIsNotProtected($role);

        try {
            DB::beginTransaction();

            if ($request->has('name')) {
                $role->update(['name' => $request->validated('name')]);
            }

            if ($request->has('permissions')) {
                $role->syncPermissions($request->validated('permissions'));
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->serverError($e);
        }

        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    public function destroy(Role $role): Response|JsonResponse
    {
        Gate::authorize('delete', $role);

        $this->ensureIsNotProtected($role);

        try {
            DB::beginTransaction();

            // Locked read: a concurrent assignment of this role waits until we finish.
            $isAssigned = DB::table(config('permission.table_names.model_has_roles'))
                ->where('role_id', $role->getKey())
                ->lockForUpdate()
                ->exists();

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
