<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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
        Gate::define('manage-clan', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-users', fn (User $user): bool => $user->isAdmin());
        Gate::define('view-users', fn (User $user): bool => $user->canManageUserRoles());
        Gate::define('manage-user-roles', fn (User $user): bool => $user->canManageUserRoles());
        Gate::define('link-user-players', fn (User $user): bool => $user->canManageUserRoles());
        Gate::define('sync-clan-data', fn (User $user): bool => ! config('services.clash_of_clans.demo_mode')
            && $user->canSyncClanData());

        Vite::prefetch(concurrency: 3);
    }
}
