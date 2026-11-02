<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    /**
     * Permissions are a fixed catalog (seeded), so they are listed without pagination.
     */
    public function index(): AnonymousResourceCollection
    {
        // Listing permissions is part of managing roles.
        Gate::authorize('viewAny', Role::class);

        return PermissionResource::collection(Permission::orderBy('name')->get());
    }
}
