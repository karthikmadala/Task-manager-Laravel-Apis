<?php

use App\Http\Middleware\CheckTokenExpiration;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\StoreApiSessionMetadata;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: ['throttle:api']);

        $middleware->alias([
            'check.token.expiry' => CheckTokenExpiration::class,
            'role' => RoleMiddleware::class,
            'store.api.session' => StoreApiSessionMetadata::class,
        ]);

        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Global exception rendering is delegated to app/Exceptions/Handler.php.
    })
    ->create();
