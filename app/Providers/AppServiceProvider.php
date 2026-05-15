<?php

namespace App\Providers;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Policies\TransactionPolicy;
use App\Policies\WalletPolicy;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Repositories\Eloquent\TransactionRepository;
use App\Repositories\Eloquent\WalletRepository;
use App\Services\Crypto\BlockchainInfoService;
use App\Services\Crypto\Contracts\EvmRpcServiceInterface;
use App\Services\Crypto\EvmRpcService;
use App\Services\Crypto\WalletGenerationService;
use App\Services\GasEstimationService;
use App\Services\ICOService;
use App\Services\StakingService;
use App\Services\TransactionBroadcastService;
use App\Services\TransactionMonitorService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WalletRepositoryInterface::class, WalletRepository::class);
        $this->app->bind(TransactionRepositoryInterface::class, TransactionRepository::class);

        // Build one Guzzle client shared across all crypto services
        $this->app->singleton(EvmRpcService::class, fn() =>
            new EvmRpcService(EvmRpcService::buildRetryClient())
        );

        // Interface resolves to the same singleton
        $this->app->bind(EvmRpcServiceInterface::class, fn($app) =>
            $app->make(EvmRpcService::class)
        );

        // Register transaction services as singletons (EvmRpcService is now resolvable)
        $this->app->singleton(GasEstimationService::class);
        $this->app->singleton(TransactionBroadcastService::class);
        $this->app->singleton(TransactionMonitorService::class);

        // New blockchain + protocol services
        $this->app->singleton(BlockchainInfoService::class);
        $this->app->singleton(WalletGenerationService::class);
        $this->app->singleton(StakingService::class);
        $this->app->singleton(ICOService::class);
        $this->app->singleton(\App\Services\AuditLogService::class);
    }

    public function boot(): void
    {
        Gate::policy(Wallet::class, WalletPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by(
                strtolower((string) ($request->input('email') ?: $request->input('address') ?: $request->ip()))
            );
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        // Tighter limit for portfolio endpoints — ?refresh=true triggers live blockchain calls
        RateLimiter::for('portfolio', function (Request $request) {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('broadcast', function (Request $request) {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip()));
        });
    }
}
