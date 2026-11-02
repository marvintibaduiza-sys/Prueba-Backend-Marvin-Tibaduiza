<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
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
     * Whether removing or demoting this user would leave the system without administrators.
     */
    public function isLastAdmin(): bool
    {
        return $this->hasRole(RoleName::Admin->value)
            && static::role(RoleName::Admin->value)->count() === 1;
    }

    /**
     * Lock the admin role assignments until the current transaction ends, so two
     * concurrent requests cannot both pass the "last admin" check (SELECT ... FOR UPDATE).
     */
    public static function lockAdministrators(): void
    {
        DB::table(config('permission.table_names.model_has_roles'))
            ->where('role_id', Role::findByName(RoleName::Admin->value, Guard::getDefaultName(static::class))->getKey())
            ->lockForUpdate()
            ->get();
    }
}
