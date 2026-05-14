<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlockchainController;
use App\Http\Controllers\Api\V1\ChainInfoController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ICOController;
use App\Http\Controllers\Api\V1\PortfolioController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\StakingController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WalletGenerationController;
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

        // ── Blockchain read-only queries ──────────────────────────────────────
        Route::prefix('blockchain')->group(function (): void {
            Route::get('token-details', [BlockchainController::class, 'tokenDetails']);
            Route::post('balance', [BlockchainController::class, 'erc20Balance']);
            Route::post('native-balance', [BlockchainController::class, 'nativeBalance']);
            Route::post('receipt', [BlockchainController::class, 'transactionReceipt']);
            Route::get('gas-price/{chain}', [BlockchainController::class, 'gasPrice']);
        });

        // ── Wallet generation ─────────────────────────────────────────────────
        Route::prefix('wallet-gen')->group(function (): void {
            Route::get('mnemonic', [WalletGenerationController::class, 'mnemonic']);
            Route::post('address', [WalletGenerationController::class, 'createAddress']);
            Route::post('internal', [WalletGenerationController::class, 'createInternalWallet']);
            Route::post('reveal-key', [WalletGenerationController::class, 'revealKey']);
        });

        // ── Staking (prepare for MetaMask signing) ────────────────────────────
        Route::prefix('staking')->group(function (): void {
            Route::get('user', [StakingController::class, 'userDetails']);
            Route::get('plan', [StakingController::class, 'planDetails']);
            Route::middleware('throttle:broadcast')->group(function (): void {
                Route::post('prepare/stake', [StakingController::class, 'prepareStake']);
                Route::post('prepare/withdraw', [StakingController::class, 'prepareWithdraw']);
            });
        });

        // ── ICO (prepare for MetaMask signing) ───────────────────────────────
        Route::prefix('ico')->group(function (): void {
            Route::middleware('throttle:broadcast')->group(function (): void {
                Route::post('buy/prepare', [ICOController::class, 'prepareBuyTokens']);
                Route::post('buy', [ICOController::class, 'selfServiceBuyTokens']);
            });
        });

        Route::middleware('role:admin')->group(function (): void {
            Route::get('admin/health', [HealthController::class, 'admin']);
            Route::get('admin/users', [AdminController::class, 'users'])
                ->middleware('permission:users.view');
            Route::get('admin/users/{userId}', [AdminController::class, 'userDetails'])
                ->middleware('permission:users.view');
            Route::patch('admin/users/{userId}/menu-restrictions', [AdminController::class, 'updateMenuRestrictions'])
                ->middleware('permission:users.edit');
            Route::get('admin/logs', [AdminController::class, 'logs'])
                ->middleware('permission:admin.access');
            Route::get('admin/wallets', [AdminController::class, 'wallets'])
                ->middleware('permission:wallets.view');
            Route::get('admin/transactions', [AdminController::class, 'transactions'])
                ->middleware('permission:transactions.view');
            Route::get('admin/tokens', [AdminController::class, 'tokens'])
                ->middleware('permission:tokens.view');
            Route::post('admin/tokens', [AdminController::class, 'createToken'])
                ->middleware('permission:tokens.create');
            Route::put('admin/tokens/{token}', [AdminController::class, 'updateToken'])
                ->middleware('permission:tokens.edit');
            Route::delete('admin/tokens/{token}', [AdminController::class, 'deleteToken'])
                ->middleware('permission:tokens.delete');
            Route::patch('admin/tokens/{token}/status', [AdminController::class, 'toggleTokenStatus'])
                ->middleware('permission:tokens.toggle');
            // Admin-only chain metadata endpoint
            // Route::get('chains', [ChainInfoController::class, 'index']);
            // Admin wallet generation (full key pair)
            Route::post('admin/wallet-gen/keypair', [WalletGenerationController::class, 'createKeypair']);
            // Admin staking (backend-signed, uses service wallet)
            Route::post('admin/staking/stake', [StakingController::class, 'executeStake']);
            Route::post('admin/staking/withdraw', [StakingController::class, 'executeWithdraw']);
            // Admin ICO (backend-signed)
            Route::post('admin/ico/sign', [ICOController::class, 'createSign']);
            Route::post('admin/ico/buy', [ICOController::class, 'executeBuyTokens']);
            // Admin analytics dashboard
            Route::get('admin/analytics', [AdminController::class, 'analytics'])
                ->middleware('permission:admin.access');
        });
    });

});
