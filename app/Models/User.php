<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Is this the last admin? Locks the admin rows so two requests can't remove both last admins at once.
     */
    public function isLastAdminForUpdate(): bool
    {
        $adminRole = Role::findByName(RoleName::Admin->value, Guard::getDefaultName(static::class));

        // Locks the pivot rows directly: a whereHas() subquery would neither lock them
        // nor see the latest committed rows under REPEATABLE READ.
        $adminIds = RoleAssignment::ofRole($adminRole)
            ->forUsers()
            ->lockForUpdate()
            ->pluck(config('permission.column_names.model_morph_key'))
            ->map(fn ($id) => (int) $id);

        return $adminIds->count() === 1 && $adminIds->contains($this->getKey());
    }
}
