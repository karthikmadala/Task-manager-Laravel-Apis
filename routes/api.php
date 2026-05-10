<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChainInfoController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PortfolioController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', [HealthController::class, 'show']);
    Route::get('chains', [ChainInfoController::class, 'index']);

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

        // Transaction routes
        Route::prefix('transactions')->group(function (): void {
            Route::post('prepare', [TransactionController::class, 'prepare']);
            Route::get('/', [TransactionController::class, 'index']);
            Route::get('{transaction}', [TransactionController::class, 'show']);
            Route::post('{transaction}/status', [TransactionController::class, 'checkStatus']);

            Route::middleware('throttle:broadcast')->group(function (): void {
                Route::post('record', [TransactionController::class, 'record']);
                Route::post('/', [TransactionController::class, 'store']);
                Route::post('{transaction}/cancel', [TransactionController::class, 'cancel']);
                Route::post('sign', [TransactionController::class, 'sign']);
                Route::post('broadcast', [TransactionController::class, 'broadcast']);
            });
        });

        Route::middleware('role:admin')->group(function (): void {
            Route::get('admin/health', [HealthController::class, 'admin']);
            Route::get('admin/users', [AdminController::class, 'users']);
            Route::get('admin/users/{user}', [AdminController::class, 'userDetails']);
            Route::get('admin/logs', [AdminController::class, 'logs']);
            Route::get('admin/wallets', [AdminController::class, 'wallets']);
            Route::get('admin/tokens', [AdminController::class, 'tokens']);
            Route::post('admin/tokens', [AdminController::class, 'createToken']);
            Route::put('admin/tokens/{token}', [AdminController::class, 'updateToken']);
            Route::delete('admin/tokens/{token}', [AdminController::class, 'deleteToken']);
            Route::patch('admin/tokens/{token}/status', [AdminController::class, 'toggleTokenStatus']);
            // Admin‑only chain metadata endpoint
            Route::get('chains', [ChainInfoController::class, 'index']);
        });
    });

    // Webhook endpoints (no auth required, signature validation)
    Route::post('webhooks/alchemy', [TransactionController::class, 'alchemyWebhook']);
    Route::post('webhooks/etherscan', [TransactionController::class, 'etherscanWebhook']);
});
