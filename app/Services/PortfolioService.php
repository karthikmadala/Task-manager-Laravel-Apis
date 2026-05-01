<?php

namespace App\Services;

use App\Enums\ChainType;
use App\Models\Token;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalance;
use App\Services\Crypto\AlchemyService;
use App\Services\Crypto\BlockCypherService;
use App\Services\Crypto\Contracts\EvmRpcServiceInterface;
use App\Services\Crypto\ExplorerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Error handling strategy:
 *
 * - fetchNativeBalance / fetchErc20Balances: each provider is wrapped in try/catch.
 *   On exception the warning is logged and the next provider is attempted.
 *   If all three providers fail the method returns '0' and logs an error.
 *
 * - syncWallet: the entire sync is wrapped in try/catch so a single wallet
 *   failure never crashes the scheduler or queue job.
 *
 * - getWalletPortfolio / getPortfolio / getBalancesForAddress: exceptions propagate
 *   to Handler.php which returns a 500 JSON response via api_response().
 */
class PortfolioService
{
    private const CACHE_TTL = 60;

    public function __construct(
        private readonly AlchemyService        $alchemy,
        private readonly ExplorerService       $explorer,
        private readonly EvmRpcServiceInterface $evmRpc,
        private readonly BlockCypherService    $blockCypher,
    ) {}

    // ─── Public API ──────────────────────────────────────────────────────────

    /**
     * Return structured portfolio for a single wallet.
     * Reads from cached wallet_balances; pass $refresh = true to re-sync first.
     *
     * @return array{ wallet: array, balances: array, total_value_usd: string }
     */
    public function getWalletPortfolio(Wallet $wallet, bool $refresh = false): array
    {
        $cacheKey = "portfolio:{$wallet->id}";

        if ($refresh) {
            $this->syncWallet($wallet);
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($wallet): array {
            $wallet->loadMissing('balances.token');

            return $this->buildWalletResponse($wallet);
        });
    }

    /**
     * Return aggregated portfolio across all active wallets for a user.
     *
     * @return array{ total_value_usd: string, wallet_count: int, wallets: array }
     */
    public function getPortfolio(User $user, bool $refresh = false): array
    {
        $cacheKey = "portfolio:user:{$user->id}";

        if ($refresh) {
            $this->activeWallets($user)->each(fn(Wallet $w) => $this->syncWallet($w));
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user): array {
            $wallets = $this->activeWallets($user);
            $totalUsd = '0';

            $walletData = $wallets->map(function (Wallet $wallet) use (&$totalUsd): array {
                $breakdown = $this->buildWalletResponse($wallet);
                $totalUsd  = bcadd($totalUsd, $breakdown['total_value_usd'], 8);

                return $breakdown;
            });

            return [
                'total_value_usd' => $this->formatUsd($totalUsd),
                'wallet_count'    => $wallets->count(),
                'wallets'         => $walletData->values()->all(),
            ];
        });
    }

    /**
     * Return live on-chain balances for an arbitrary address without persisting.
     * Used by ChainInfoController for address lookups.
     *
     * @return array<int, array{ symbol: string, name: string, contract: string|null, balance: string, price_usd: float|null, value_usd: string|null }>
     */
    public function getBalancesForAddress(string $address, ChainType $chain, bool $refresh = false): array
    {
        $cacheKey = 'address:' . $chain->value . ':' . strtolower($address);

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($address, $chain): array {
            $tokens = Token::where('chain_type', $chain->value)->get();

            if ($tokens->isEmpty()) {
                return [];
            }

            $rawBalances = $chain === ChainType::BTC
                ? $this->fetchBtcRawBalances($address, $tokens)
                : $this->fetchEvmRawBalances($address, $chain, $tokens);

            return $tokens->map(function (Token $token) use ($rawBalances): array {
                $raw      = $rawBalances[$token->id] ?? '0';
                $balance  = $this->evmRpc->toDecimalUnits($raw, $token->decimals);
                $price    = $token->current_price_usd;
                $valueUsd = $price !== null
                    ? $this->formatUsd(bcmul($balance, (string) $price, 8))
                    : null;

                return [
                    'symbol'    => $token->symbol,
                    'name'      => $token->name,
                    'contract'  => $token->contract_address,
                    'balance'   => $balance,
                    'price_usd' => $price,
                    'value_usd' => $valueUsd,
                ];
            })->all();
        });
    }

    /**
     * Fetch on-chain balances and persist them to wallet_balances.
     * Exception-safe — failures are logged and do not propagate.
     */
    public function syncWallet(Wallet $wallet): void
    {
        try {
            $tokens = Token::where('chain_type', $wallet->chain_type->value)->get();

            if ($tokens->isEmpty()) {
                return;
            }

            $rawBalances = $wallet->chain_type === ChainType::BTC
                ? $this->fetchBtcRawBalances($wallet->address, $tokens)
                : $this->fetchEvmRawBalances($wallet->address, $wallet->chain_type, $tokens);

            foreach ($tokens as $token) {
                $raw      = $rawBalances[$token->id] ?? '0';
                $balance  = $this->evmRpc->toDecimalUnits($raw, $token->decimals);
                $price    = $token->current_price_usd;
                $valueUsd = $price !== null
                    ? bcmul($balance, (string) $price, 8)
                    : null;

                WalletBalance::updateOrCreate(
                    ['wallet_id' => $wallet->id, 'token_id' => $token->id],
                    ['balance' => $balance, 'balance_usd' => $valueUsd, 'fetched_at' => now()],
                );
            }

            Cache::forget("portfolio:{$wallet->id}");
            Cache::forget("portfolio:user:{$wallet->user_id}");
        } catch (\Throwable $e) {
            Log::error('Wallet sync failed', [
                'wallet_id' => $wallet->id,
                'chain'     => $wallet->chain_type->value,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    // ─── Response builders ───────────────────────────────────────────────────

    /**
     * Assemble the structured portfolio response for a single wallet from DB.
     * Expects balances.token to already be loaded.
     *
     * @return array{ wallet: array, balances: array, total_value_usd: string }
     */
    private function buildWalletResponse(Wallet $wallet): array
    {
        $totalUsd   = '0';
        $lastSynced = null;

        $balances = $wallet->balances
            ->filter(fn(WalletBalance $wb) => bccomp((string) $wb->balance, '0', 18) > 0)
            ->sortByDesc(fn(WalletBalance $wb) => (float) ($wb->balance_usd ?? '0'))
            ->map(function (WalletBalance $wb) use (&$totalUsd, &$lastSynced): array {
                $valueUsd = (string) ($wb->balance_usd ?? '0');
                $totalUsd = bcadd($totalUsd, $valueUsd, 8);

                if ($lastSynced === null || $wb->fetched_at?->gt($lastSynced)) {
                    $lastSynced = $wb->fetched_at;
                }

                return [
                    'symbol'         => $wb->token->symbol,
                    'name'           => $wb->token->name,
                    'contract'       => $wb->token->contract_address,
                    'balance'        => (string) $wb->balance,
                    'price_usd'      => $wb->token->current_price_usd,
                    'value_usd'      => $this->formatUsd($valueUsd),
                    'last_synced_at' => $wb->fetched_at?->toISOString(),
                ];
            })
            ->values()
            ->all();

        return [
            'wallet'          => $this->walletMeta($wallet, $lastSynced),
            'balances'        => $balances,
            'total_value_usd' => $this->formatUsd($totalUsd),
        ];
    }

    /**
     * @param  \Illuminate\Support\Carbon|null  $lastSynced
     */
    private function walletMeta(Wallet $wallet, mixed $lastSynced): array
    {
        return [
            'id'            => $wallet->id,
            'address'       => $wallet->address,
            'chain_type'    => $wallet->chain_type->value,
            'chain_label'   => $wallet->chain_type->label(),
            'wallet_type'   => $wallet->wallet_type->value,
            'label'         => $wallet->label,
            'last_synced_at' => $lastSynced?->toISOString(),
        ];
    }

    // ─── Balance fetchers ────────────────────────────────────────────────────

    /**
     * Fetch EVM on-chain balances for a wallet/address, returning [ token_id => raw_wei_string ].
     */
    private function fetchEvmRawBalances(string $address, ChainType $chain, Collection $tokens): array
    {
        $balances = [];

        $nativeToken = $tokens->first(fn(Token $t) => $t->isNative());
        if ($nativeToken) {
            $balances[$nativeToken->id] = $this->fetchNativeBalance($address, $chain);
        }

        $erc20Tokens   = $tokens->filter(fn(Token $t) => ! $t->isNative());
        $contractAddrs = $erc20Tokens->pluck('contract_address')->values()->all();

        if (! empty($contractAddrs)) {
            $fetched = $this->fetchErc20Balances($address, $contractAddrs, $chain);

            foreach ($erc20Tokens as $token) {
                $balances[$token->id] = $fetched[strtolower((string) $token->contract_address)] ?? '0';
            }
        }

        return $balances;
    }

    /**
     * Fetch BTC balance, returning [ token_id => satoshi_string ].
     */
    private function fetchBtcRawBalances(string $address, Collection $tokens): array
    {
        $btcToken = $tokens->first(fn(Token $t) => $t->symbol === 'BTC');

        if (! $btcToken) {
            return [];
        }

        $data = $this->blockCypher->getBalance($address);

        return [$btcToken->id => $data['final_balance']];
    }

    /**
     * Fetch native token balance using provider priority: Alchemy → Explorer → RPC.
     * Returns '0' if all providers fail.
     */
    private function fetchNativeBalance(string $address, ChainType $chain): string
    {
        // 1. Alchemy — ETH + Polygon only
        if ($this->alchemySupports($chain)) {
            try {
                return $this->alchemy->getNativeBalance($address, $chain);
            } catch (\Throwable $e) {
                Log::warning('Alchemy native balance failed, trying explorer', [
                    'chain' => $chain->value, 'error' => $e->getMessage(),
                ]);
            }
        }

        // 2. Block Explorer API (Etherscan / BscScan / PolygonScan)
        if (config("crypto.explorer.{$chain->value}.key")) {
            try {
                return $this->explorer->getNativeBalance($address, $chain);
            } catch (\Throwable $e) {
                Log::warning('Explorer native balance failed, falling back to RPC', [
                    'chain' => $chain->value, 'error' => $e->getMessage(),
                ]);
            }
        }

        // 3. Direct JSON-RPC fallback (always available)
        try {
            return $this->evmRpc->getNativeBalance($address, $chain);
        } catch (\Throwable $e) {
            Log::error('All providers failed for native balance', [
                'chain' => $chain->value, 'address' => $address, 'error' => $e->getMessage(),
            ]);

            return '0';
        }
    }

    /**
     * Fetch ERC-20 balances using provider priority: Alchemy batch → Explorer → RPC batch.
     * Returns [ lowercase_contract => decimal_balance ].
     */
    private function fetchErc20Balances(string $address, array $contracts, ChainType $chain): array
    {
        // 1. Alchemy batch — ETH + Polygon only
        if ($this->alchemySupports($chain)) {
            try {
                return $this->alchemy->getTokenBalances($address, $chain, $contracts);
            } catch (\Throwable $e) {
                Log::warning('Alchemy ERC-20 batch failed, trying explorer', [
                    'chain' => $chain->value, 'error' => $e->getMessage(),
                ]);
            }
        }

        // 2. Explorer API
        if (config("crypto.explorer.{$chain->value}.key")) {
            try {
                return $this->explorer->getTokenBalances($address, $contracts, $chain);
            } catch (\Throwable $e) {
                Log::warning('Explorer ERC-20 balances failed, falling back to RPC', [
                    'chain' => $chain->value, 'error' => $e->getMessage(),
                ]);
            }
        }

        // 3. Direct JSON-RPC batch fallback
        try {
            return $this->evmRpc->getErc20Balances($address, $contracts, $chain);
        } catch (\Throwable $e) {
            Log::error('All providers failed for ERC-20 balances', [
                'chain' => $chain->value, 'address' => $address, 'error' => $e->getMessage(),
            ]);

            return array_fill_keys(array_map('strtolower', $contracts), '0');
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function alchemySupports(ChainType $chain): bool
    {
        return \in_array($chain, [ChainType::ETH, ChainType::POLYGON], true)
            && (bool) config('crypto.alchemy.key');
    }

    private function activeWallets(User $user): Collection
    {
        return $user->wallets()
            ->where('is_active', true)
            ->with(['balances.token'])
            ->get();
    }

    private function formatUsd(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
