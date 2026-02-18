<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        Gate::define('operations.manage', fn (User $user) => $this->isManagerOrOwner($user));
        Gate::define('approvals.manage', fn (User $user) => $this->isManagerOrOwner($user));
        Gate::define('compliance.view', fn (User $user) => $this->isManagerOrOwner($user));
        Gate::define('notifications.manage', fn (User $user) => $this->isManagerOrOwner($user));
        Gate::define('integrations.manage', fn (User $user) => $this->isManagerOrOwner($user));
        Gate::define('templates.manage', fn (User $user) => $this->isManagerOrOwner($user));
        Gate::define('wallets.manage', fn (User $user) => $this->isManagerOrOwner($user));
        Gate::define('bulk.manage', fn (User $user) => $this->isManagerOrOwner($user));
        Gate::define('cost-reports.view', fn (User $user) => $this->isManagerOrOwner($user));
        Gate::define('roles.manage', fn (User $user) => $this->isOwner($user));
        Gate::define('exports.manage', fn (User $user) => $this->isManagerOrOwner($user));
        Gate::define('system-health.view', fn (User $user) => $this->isManagerOrOwner($user));
    }

    protected function isOwner(User $user): bool
    {
        return $user->normalizedRole() === User::ROLE_COMPANY_OWNER;
    }

    protected function isManagerOrOwner(User $user): bool
    {
        return in_array($user->normalizedRole(), [User::ROLE_COMPANY_OWNER, User::ROLE_COMPANY_MANAGER], true);
    }
}
