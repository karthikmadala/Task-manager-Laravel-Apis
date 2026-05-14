<?php

namespace App\Services;

use App\Enums\ChainType;
use App\Models\Token;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalance;
use App\Services\Crypto\BlockchainNodeService;
use App\Services\Crypto\BlockCypherService;
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
        private readonly BlockchainNodeService $node,
        private readonly ExplorerService       $explorer,
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
            $wallet->load('balances.token');

            return $this->buildWalletResponse($wallet);
        });
    }

    /**
     * Return aggregated portfolio across all active wallets for a user.
     *
     * @return array{ total_value_usd: string, wallet_count: int, grouped_wallet_count: int, wallets: array, grouped_wallets: array, chain_totals: array }
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
            $walletItems = $walletData->values()->all();
            $groupedWallets = $this->groupWalletResponsesByAddress($walletItems);

            return [
                'total_value_usd' => $this->formatUsd($totalUsd),
                'wallet_count'    => $wallets->count(),
                'grouped_wallet_count' => count($groupedWallets),
                'wallets'         => $walletItems,
                'grouped_wallets' => $groupedWallets,
                'chain_totals'    => $this->computeChainTotals($walletItems),
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
$tokens = Token::where('chain_type', $chain->value)->where('enabled', true)->get();

            if ($tokens->isEmpty()) {
                return [];
            }

            $rawBalances = $chain === ChainType::BTC
                ? $this->fetchBtcRawBalances($address, $tokens)
                : $this->fetchEvmRawBalances($address, $chain, $tokens);
            $priceMap = $chain === ChainType::BTC
                ? []
                : $this->explorer->getUsdPriceMapForTokens($address, $chain, $tokens);

            return $tokens->map(function (Token $token) use ($rawBalances, $priceMap, $chain): array {
                $raw      = $rawBalances[$token->id] ?? '0';
                $balance  = $this->toDecimalUnits($raw, $token->decimals);
                $price    = $this->resolveTokenPriceUsd($token, $priceMap, $chain);
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
            if ($wallet->chain_type === ChainType::BTC) {
                $this->syncWalletChain($wallet, ChainType::BTC);
            } else {
                foreach ($this->evmChains() as $chain) {
                    $this->syncWalletChain($wallet, $chain);
                }
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
            ->filter(fn(WalletBalance $wb) => bccomp((string) $wb->balance, '0', 18) > 0 && $wb->token->enabled)
            ->sortByDesc(fn(WalletBalance $wb) => (float) ($wb->balance_usd ?? '0'))
            ->map(function (WalletBalance $wb) use (&$totalUsd, &$lastSynced): array {
                $price = $this->resolveWalletBalancePriceUsd($wb);
                $valueUsd = $price !== null
                    ? bcmul((string) $wb->balance, (string) $price, 8)
                    : (string) ($wb->balance_usd ?? '0');
                $totalUsd = bcadd($totalUsd, $valueUsd, 8);

                if ($lastSynced === null || $wb->fetched_at?->gt($lastSynced)) {
                    $lastSynced = $wb->fetched_at;
                }

                return [
                    'chain_type' => $wb->chain_type?->value,
                    'symbol' => $wb->token->symbol,
                    'name' => $wb->token->name,
                    'contract' => $wb->token->contract_address,
                    'balance' => (string) $wb->balance,
                    'price_usd' => $price,
                    'value_usd' => $this->formatUsd($valueUsd),
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
     * Fetch EVM on-chain balances via node (Alchemy → Explorer → RPC fallback).
     * Returns [ token_id => raw_wei_string ].
     */
    private function fetchEvmRawBalances(string $address, ChainType $chain, Collection $tokens): array
    {
        $nativeToken   = $tokens->first(fn(Token $t) => $t->isNative());
        $erc20Tokens   = $tokens->filter(fn(Token $t) => ! $t->isNative());
        $contractAddrs = $erc20Tokens->pluck('contract_address')->values()->all();

        $result = $this->node->fetchPortfolioBalances($chain, $address, $contractAddrs);

        $balances = [];

        if ($nativeToken) {
            $balances[$nativeToken->id] = $result['native_balance'] ?? '0';
        }

        foreach ($erc20Tokens as $token) {
            $balances[$token->id] = $result['token_balances'][strtolower((string) $token->contract_address)] ?? '0';
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

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function toDecimalUnits(string $rawAmount, int $decimals): string
    {
        if ($rawAmount === '0') {
            return '0';
        }

        return bcdiv($rawAmount, bcpow('10', (string) $decimals), $decimals);
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

    /**
     * @param  array<int, array{wallet: array, balances: array, total_value_usd: string}>  $wallets
     * @return array<int, array<string, mixed>>
     */
    private function groupWalletResponsesByAddress(array $wallets): array
    {
        $grouped = [];

        foreach ($wallets as $walletPortfolio) {
            $wallet = $walletPortfolio['wallet'];
            $addressKey = strtolower((string) $wallet['address']);

            if (! isset($grouped[$addressKey])) {
                $grouped[$addressKey] = [
                    'address' => $wallet['address'],
                    'label' => $wallet['label'],
                    'total_value_usd' => '0.00',
                    'chains' => [],
                    'wallets' => [],
                    'balances' => [],
                    'main_id' => $wallet['id'],
                ];
            }

            $grouped[$addressKey]['chains'][] = $wallet['chain_type'];
            $grouped[$addressKey]['wallets'][] = $walletPortfolio;
            $grouped[$addressKey]['total_value_usd'] = $this->formatUsd(bcadd(
                $grouped[$addressKey]['total_value_usd'],
                (string) $walletPortfolio['total_value_usd'],
                8
            ));

            foreach ($walletPortfolio['balances'] as $balance) {
                $grouped[$addressKey]['balances'][] = array_merge($balance, [
                    'chain' => $wallet['chain_type'],
                ]);
            }
        }

        foreach ($grouped as &$group) {
            $group['chains'] = array_values(array_unique($group['chains']));
            usort($group['balances'], function (array $left, array $right): int {
                return (float) ($right['value_usd'] ?? '0') <=> (float) ($left['value_usd'] ?? '0');
            });
        }
        unset($group);

        usort($grouped, function (array $left, array $right): int {
            return (float) ($right['total_value_usd'] ?? '0') <=> (float) ($left['total_value_usd'] ?? '0');
        });

        return array_values($grouped);
    }

    /**
     * @param  array<string, float|null>  $priceMap
     */
    private function resolveTokenPriceUsd(Token $token, array $priceMap, ChainType $chain): ?float
    {
        if ($token->isNative()) {
            return $priceMap[$this->explorer->nativePriceMapKey($chain)] ?? $token->current_price_usd;
        }

        $contract = strtolower((string) $token->contract_address);

        return $priceMap[$contract] ?? $token->current_price_usd;
    }

    private function resolveWalletBalancePriceUsd(WalletBalance $walletBalance): ?float
    {
        if ($walletBalance->token->current_price_usd !== null) {
            return (float) $walletBalance->token->current_price_usd;
        }

        if (bccomp((string) $walletBalance->balance, '0', 18) === 0) {
            return null;
        }

        if ($walletBalance->balance_usd === null) {
            return null;
        }

        return (float) bcdiv((string) $walletBalance->balance_usd, (string) $walletBalance->balance, 8);
    }

    /**
     * @param  array<int, array{wallet: array, balances: array, total_value_usd: string}>  $walletItems
     * @return array<int, array{chain_type: string, total_value_usd: string}>
     */
    private function computeChainTotals(array $walletItems): array
    {
        $totals = [];

        foreach ($walletItems as $walletItem) {
            foreach ($walletItem['balances'] as $balance) {
                $chain = $balance['chain_type'] ?? $walletItem['wallet']['chain_type'];
                if (! isset($totals[$chain])) {
                    $totals[$chain] = '0';
                }

                $totals[$chain] = bcadd($totals[$chain], (string) ($balance['value_usd'] ?? '0'), 8);
            }
        }

        $ordered = [];
        foreach ([ChainType::ETH, ChainType::BNB, ChainType::POLYGON, ChainType::BTC] as $chain) {
            if (! isset($totals[$chain->value])) {
                continue;
            }

            $ordered[] = [
                'chain_type' => $chain->value,
                'total_value_usd' => $this->formatUsd($totals[$chain->value]),
            ];
        }

        return $ordered;
    }

    private function syncWalletChain(Wallet $wallet, ChainType $chain): void
    {
        $tokens = Token::where('chain_type', $chain->value)->where('enabled', true)->get();

        if ($tokens->isEmpty()) {
            return;
        }

        $rawBalances = $chain === ChainType::BTC
            ? $this->fetchBtcRawBalances($wallet->address, $tokens)
            : $this->fetchEvmRawBalances($wallet->address, $chain, $tokens);
        $priceMap = $chain === ChainType::BTC
            ? []
            : $this->explorer->getUsdPriceMapForTokens($wallet->address, $chain, $tokens);

        foreach ($tokens as $token) {
            $raw      = $rawBalances[$token->id] ?? '0';
            $balance  = $this->toDecimalUnits($raw, $token->decimals);
            $price    = $chain === ChainType::BTC ? $token->current_price_usd : $this->resolveTokenPriceUsd($token, $priceMap, $chain);
            $valueUsd = $price !== null
                ? bcmul($balance, (string) $price, 8)
                : null;

            WalletBalance::updateOrCreate(
                ['wallet_id' => $wallet->id, 'chain_type' => $chain->value, 'token_id' => $token->id],
                ['balance' => $balance, 'balance_usd' => $valueUsd, 'fetched_at' => now()],
            );
        }
    }

    /**
     * @return array<int, ChainType>
     */
    private function evmChains(): array
    {
        return [ChainType::ETH, ChainType::BNB, ChainType::POLYGON];
    }
}
