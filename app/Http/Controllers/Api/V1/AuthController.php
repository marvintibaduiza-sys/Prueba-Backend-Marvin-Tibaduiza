<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthTokenResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Bcrypt hash of a random value (cost 12), checked when the email is unknown so both
     * login paths take the same time and response timing cannot reveal existing accounts.
     */
    private const DUMMY_HASH = '$2y$12$p8vUU3vDIOy1x4RXQef0Q.4QuaEEvapr5e5vB4nsiaufAIyFGLvlu';

    /**
     * Exchange valid credentials for an expiring Sanctum token.
     */
    public function login(LoginRequest $request): AuthTokenResource
    {
        $user = User::where('email', $request->validated('email'))->first();
        $passwordMatches = Hash::check($request->validated('password'), $user?->password ?? self::DUMMY_HASH);

        // Same message for unknown email and wrong password: never reveal which accounts exist.
        if (! $user || ! $passwordMatches) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $token = $user->createToken('api', ['*'], now()->addMinutes(config('sanctum.expiration')));

        return new AuthTokenResource($token, $user->load('roles.permissions'));
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Return the authenticated user with roles and effective permissions.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles.permissions'));
    }
}
