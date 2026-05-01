<?php

namespace App\Providers;

use App\Models\Wallet;
use App\Policies\WalletPolicy;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Repositories\Eloquent\WalletRepository;
use App\Services\Crypto\Contracts\EvmRpcServiceInterface;
use App\Services\Crypto\EvmRpcService;
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

        $this->app->bind(EvmRpcServiceInterface::class, fn() =>
            new EvmRpcService(EvmRpcService::buildRetryClient())
        );
    }

    public function boot(): void
    {
        Gate::policy(Wallet::class, WalletPolicy::class);

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
