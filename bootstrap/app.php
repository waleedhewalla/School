<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveCurrentSchool;
use App\Http\Middleware\ResolvePublicSchool;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'school' => ResolveCurrentSchool::class,
            'public-school' => ResolvePublicSchool::class,
            'locale' => SetLocale::class,
        ]);

        $middleware->web(append: [SetLocale::class, HandleInertiaRequests::class]);

        // Behind a load balancer / TLS proxy: TRUSTED_PROXIES="*" or a list of IPs.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }

        // Order: auth → locale → school → route model binding. The school must
        // be known before binding so bound models are filtered to it.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetLocale::class,
        );
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveCurrentSchool::class,
        );
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolvePublicSchool::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
