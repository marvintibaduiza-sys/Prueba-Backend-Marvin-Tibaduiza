<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * Model for spatie's model_has_roles table (who has which role). Read only.
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
     * Only users, the table can hold other models too.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForUsers(Builder $query): void
    {
        $query->where('model_type', (new User)->getMorphClass());
    }
}
