<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Root of the OpenAPI document: general info, server, authentication scheme,
 * tags and the error responses shared by every endpoint.
 */
#[OA\Info(
    version: '1.0.0',
    description: <<<'TEXT'
        RESTful API to manage products and their prices in multiple currencies.

        **Authentication:** call `POST /auth/login` to obtain a Bearer token (valid for 60 minutes),
        then send it in the `Authorization: Bearer <token>` header. `POST /auth/logout` revokes it.

        **Authorization:** every endpoint requires a permission. The `admin` role has all of them;
        the `seller` role can view, create and update products, view and register prices, and view currencies.

        **Money:** amounts are returned as strings with fixed decimals (e.g. `"19.90"`) to avoid floating-point errors.

        **Errors:** every error body has a `message`; validation errors (422) also include `errors` per field.
        TEXT,
    title: 'Products API',
)]
#[OA\Server(url: '/api/v1', description: 'API version 1')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    description: 'Sanctum token obtained from POST /auth/login. Expires after 60 minutes.',
    bearerFormat: 'Sanctum token',
    scheme: 'bearer',
)]
#[OA\Tag(name: 'Auth', description: 'Login, logout and current user')]
#[OA\Tag(name: 'Products', description: 'Product management')]
#[OA\Tag(name: 'Product prices', description: 'Prices of a product in other currencies')]
#[OA\Tag(name: 'Currencies', description: 'Currency management (USD is the base currency)')]
#[OA\Tag(name: 'Users', description: 'User management (admin)')]
#[OA\Tag(name: 'Roles', description: 'Roles and permissions management (admin)')]
#[OA\Parameter(
    parameter: 'PerPage',
    name: 'per_page',
    description: 'Items per page (1-100, default 15).',
    in: 'query',
    required: false,
    schema: new OA\Schema(type: 'integer', default: 15, maximum: 100, minimum: 1),
)]
#[OA\Parameter(
    parameter: 'Page',
    name: 'page',
    description: 'Page number.',
    in: 'query',
    required: false,
    schema: new OA\Schema(type: 'integer', default: 1, minimum: 1),
)]
#[OA\Response(response: 'Unauthenticated', description: 'Missing, invalid or expired token.', content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Unauthenticated.']))]
#[OA\Response(response: 'Forbidden', description: 'The user lacks the required permission.', content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'This action is unauthorized.']))]
#[OA\Response(response: 'NotFound', description: 'The resource does not exist.', content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Resource not found.']))]
#[OA\Response(response: 'ValidationFailed', description: 'The request data is invalid.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
#[OA\Response(response: 'Conflict', description: 'The action conflicts with the current state of the resource.', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
#[OA\Response(response: 'TooManyRequests', description: 'Rate limit exceeded.', content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Too Many Attempts.']))]
#[OA\Response(response: 'ServerError', description: 'Unexpected error; details are logged, never returned.', content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'An unexpected error occurred.']))]
class OpenApiSpec {}
