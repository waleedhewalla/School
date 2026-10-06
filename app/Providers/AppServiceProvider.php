<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Messaging\LogSmsGateway;
use App\Support\Messaging\SmsGateway;
use App\Support\Messaging\TaqnyatSmsGateway;
use App\Support\Messaging\UnifonicSmsGateway;
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

        $this->app->singleton(SmsGateway::class, fn () => match (config('services.sms.driver')) {
            'unifonic' => new UnifonicSmsGateway(
                (string) config('services.sms.unifonic.app_sid'),
                (string) config('services.sms.unifonic.sender_id'),
                (string) config('services.sms.unifonic.url'),
            ),
            'taqnyat' => new TaqnyatSmsGateway(
                (string) config('services.sms.taqnyat.token'),
                (string) config('services.sms.taqnyat.sender'),
                (string) config('services.sms.taqnyat.url'),
            ),
            default => new LogSmsGateway,
        });
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
