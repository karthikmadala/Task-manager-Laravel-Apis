<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CheckTokenExpiration;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\StoreApiSessionMetadata;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: ['throttle:api', LogApiRequest::class]);

        $middleware->alias([
            'check.token.expiry' => CheckTokenExpiration::class,
            'role'               => RoleMiddleware::class,
            'permission'        => CheckPermission::class,
            'checkRole'          => CheckRole::class,
            'store.api.session'  => StoreApiSessionMetadata::class,
        ]);

        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pure JSON API — always respond with JSON regardless of Accept header.

        $exceptions->render(function (ValidationException $exception) {
            return api_response(false, 'Validation failed.', null, $exception->errors(), 422);
        });

        $exceptions->render(function (AuthenticationException $_) {
            return api_response(false, 'Unauthenticated.', null, [
                'auth' => ['Authentication is required.'],
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $_) {
            return api_response(false, 'Forbidden.', null, [
                'authorization' => ['You do not have permission for this action.'],
            ], 403);
        });

        $exceptions->render(function (\Throwable $exception) {
            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500;

            return api_response(
                false,
                $status >= 500 ? 'Server error.' : $exception->getMessage(),
                null,
                config('app.debug')
                    ? ['exception' => [$exception->getMessage()]]
                    : ['server' => ['An unexpected error occurred.']],
                $status
            );
        });
    })
    ->create();
