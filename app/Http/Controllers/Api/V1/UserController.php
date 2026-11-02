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
use Throwable;

class UserController extends Controller
{
    private const LAST_ADMIN_MESSAGE = 'The last administrator cannot be removed or demoted.';

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        return UserResource::collection(
            User::with('roles.permissions')->orderBy('id')->paginate($this->perPage($request))
        );
    }

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

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load('roles.permissions'));
    }

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
