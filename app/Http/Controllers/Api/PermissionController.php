<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    /**
     * Permissions are a fixed catalog (seeded), so they are listed without pagination.
     */
    #[OA\Get(
        path: '/permissions',
        operationId: 'permissions.index',
        description: 'Requires `roles.view`. Fixed catalog of permissions (seeded, never created through the API), sorted by name and not paginated.',
        summary: 'List permissions',
        security: [['sanctum' => []]],
        tags: ['Roles'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Every permission.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Permission')),
                ]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(): AnonymousResourceCollection
    {
        // Listing permissions is part of managing roles.
        Gate::authorize('viewAny', Role::class);

        return PermissionResource::collection(Permission::orderBy('name')->get());
    }
}
