<?php

namespace App\Providers;

use App\Enums\PermissionName;
use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        Password::defaults(fn () => Password::min(8));

        // Role is a spatie model, outside App\Models, so its policy is not auto-discovered.
        Gate::policy(Role::class, RolePolicy::class);

        // Route ids must be digits only: MySQL casts '1abc' to 1 when comparing with an
        // integer key, so /products/1abc would otherwise load (or delete) product 1.
        Route::patterns([
            'product' => '[0-9]+',
            'currency' => '[0-9]+',
            'user' => '[0-9]+',
            'role' => '[0-9]+',
        ]);

        $this->bindManagedModels();
    }

    /**
     * Users without user/role permissions get 403 for any id, so they can't guess which ids exist.
     */
    private function bindManagedModels(): void
    {
        $bindings = [
            'user' => [User::class, [PermissionName::UsersView, PermissionName::UsersUpdate, PermissionName::UsersDelete]],
            'role' => [Role::class, [PermissionName::RolesView, PermissionName::RolesUpdate, PermissionName::RolesDelete]],
        ];

        foreach ($bindings as $parameter => [$model, $permissions]) {
            Route::bind($parameter, function (string $value) use ($model, $permissions) {
                $canManage = request()->user()?->hasAnyPermission(
                    array_map(fn (PermissionName $permission) => $permission->value, $permissions)
                );

                if (! $canManage) {
                    throw new AuthorizationException;
                }

                return $model::findOrFail($value);
            });
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // Brute-force protection: 5 login attempts per minute for each email + IP pair.
        // A non-string email (e.g. an array) still gets a key, so it is counted and then rejected with 422.
        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');

            return Limit::perMinute(5)->by(Str::lower(is_string($email) ? $email : '').'|'.$request->ip());
        });
    }
}
