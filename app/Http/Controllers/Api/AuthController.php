<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthTokenResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    /**
     * Fake hash for unknown emails, so login takes the same time and doesn't reveal which emails exist.
     */
    private const DUMMY_HASH = '$2y$12$p8vUU3vDIOy1x4RXQef0Q.4QuaEEvapr5e5vB4nsiaufAIyFGLvlu';

    #[OA\Post(
        path: '/auth/login',
        operationId: 'auth.login',
        description: 'Public endpoint. Returns a Bearer token valid for 60 minutes. Unknown email and wrong password get the same 422 error on `email` ("These credentials do not match our records."), so existing accounts are never revealed. Limited to 5 attempts per minute for each email + IP pair.',
        summary: 'Log in',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')),
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token issued.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/AuthToken')]),
            ),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
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

    #[OA\Post(
        path: '/auth/logout',
        operationId: 'auth.logout',
        description: 'Revokes only the token used for this request; other sessions of the user stay active. No permission required.',
        summary: 'Log out',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token revoked.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Logged out.')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ],
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    #[OA\Get(
        path: '/auth/me',
        operationId: 'auth.me',
        description: 'Returns the authenticated user with roles and effective permissions. No permission required.',
        summary: 'Current user',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The authenticated user.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ],
    )]
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles.permissions'));
    }
}
