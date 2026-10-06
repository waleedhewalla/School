<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One active school per request (or queued job); never shared across requests.
        $this->app->scoped(CurrentSchool::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Platform staff (support/onboarding) may act in any school they
        // have entered; everyone else goes through their school roles.
        Gate::before(fn (User $user) => $user->is_platform_admin ? true : null);
    }
}
