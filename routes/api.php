<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PortfolioController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ChainInfoController;

Route::prefix('v1')->group(function (): void {
    Route::get('health', [HealthController::class, 'show']);

    Route::prefix('auth')->group(function (): void {
        Route::middleware('throttle:auth')->group(function (): void {
            Route::post('register', [AuthController::class, 'register']);
            Route::post('login', [AuthController::class, 'login']);
            Route::post('metamask/nonce', [AuthController::class, 'metamaskNonce']);
            Route::post('metamask/verify', [AuthController::class, 'metamaskVerify']);
        });

        Route::middleware(['auth:sanctum', 'check.token.expiry', 'store.api.session'])->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
        });
    });

    Route::middleware(['auth:sanctum', 'check.token.expiry', 'store.api.session'])->group(function (): void {
        Route::prefix('profile')->group(function (): void {
            Route::get('/', [ProfileController::class, 'show']);
            Route::put('/', [ProfileController::class, 'update']);
            Route::patch('change-password', [ProfileController::class, 'changePassword']);
        });

        Route::prefix('wallets')->group(function (): void {
            Route::get('/', [WalletController::class, 'index']);
            Route::post('/', [WalletController::class, 'store']);
            Route::delete('{wallet}', [WalletController::class, 'destroy']);
            Route::post('metamask/nonce', [WalletController::class, 'metamaskNonce']);
            Route::post('metamask/verify', [WalletController::class, 'metamaskVerify']);
        });

        Route::middleware('throttle:portfolio')->group(function (): void {
            Route::get('portfolio', [PortfolioController::class, 'index']);
            // static segment must precede the {wallet} wildcard
            Route::get('portfolio/chain/{chain}', [PortfolioController::class, 'chain']);
            Route::get('portfolio/{wallet}', [PortfolioController::class, 'show']);
        });

        Route::get('chain/{chain}/address/{address}/info', [ChainInfoController::class, 'info']);

        Route::middleware('role:admin')->group(function (): void {
            Route::get('admin/health', [HealthController::class, 'admin']);
        });
    });
});
