<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * Eloquent model for the role assignment pivot table of spatie/laravel-permission
 * ("model_has_roles": which model holds which role).
 *
 * Used only to read assignments, mainly with row locks: assignments are written
 * through spatie (assignRole / syncRoles / removeRole).
 */
class RoleAssignment extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    public function getTable(): string
    {
        return config('permission.table_names.model_has_roles');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, config('permission.column_names.role_pivot_key') ?? 'role_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfRole(Builder $query, Role $role): void
    {
        $query->where('role_id', $role->getKey());
    }

    /**
     * Assignments that belong to users (the pivot is polymorphic).
     *
     * @param  Builder<self>  $query
     */
    public function scopeForUsers(Builder $query): void
    {
        $query->where('model_type', (new User)->getMorphClass());
    }
}
