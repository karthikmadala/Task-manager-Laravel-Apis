<?php

namespace App\Exceptions;

use App\Exceptions\BlockchainException;
use App\Exceptions\ICOException;
use App\Exceptions\StakingException;
use App\Exceptions\WalletGenerationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'private_key',
        'key',
    ];

    public function register(): void
    {
        $this->renderable(function (ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return api_response(false, 'Validation failed.', null, $e->errors(), 422);
            }
        });

        $this->renderable(function (AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return api_response(false, 'Unauthenticated.', null, [
                    'auth' => ['Authentication is required.'],
                ], 401);
            }
        });

        $this->renderable(function (ModelNotFoundException $e, $request) {
            if ($request->expectsJson()) {
                return api_response(false, 'Resource not found.', null, [
                    'resource' => ['The requested resource could not be found.'],
                ], 404);
            }
        });

        // Blockchain domain exceptions — expose message (safe, no stack trace)
        $this->renderable(function (BlockchainException $e, $request) {
            if ($request->expectsJson()) {
                return api_response(false, $e->getMessage(), null, [
                    'blockchain' => [$e->getMessage()],
                ], 422);
            }
        });

        $this->renderable(function (StakingException $e, $request) {
            if ($request->expectsJson()) {
                return api_response(false, $e->getMessage(), null, [
                    'staking' => [$e->getMessage()],
                ], 422);
            }
        });

        $this->renderable(function (ICOException $e, $request) {
            if ($request->expectsJson()) {
                return api_response(false, $e->getMessage(), null, [
                    'ico' => [$e->getMessage()],
                ], 422);
            }
        });

        $this->renderable(function (WalletGenerationException $e, $request) {
            if ($request->expectsJson()) {
                return api_response(false, $e->getMessage(), null, [
                    'wallet' => [$e->getMessage()],
                ], 422);
            }
        });

        $this->renderable(function (Throwable $e, $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $message = $status >= 500 ? 'Server error.' : $e->getMessage();

            return api_response(false, $message, null, config('app.debug')
                ? ['exception' => $e->getMessage()]
                : ['server' => ['An unexpected error occurred.']], $status);
        });
    }
}
