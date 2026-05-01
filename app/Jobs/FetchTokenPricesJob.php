<?php

namespace App\Jobs;

use App\Models\Token;
use App\Services\Crypto\CoinGeckoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchTokenPricesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Back-off respects CoinGecko's rate-limit window (60s for demo keys).
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 120, 300];

    /**
     * Prevent overlapping price-fetch jobs from stacking in the queue.
     * One fetch run at a time — 10 minutes is well within the CoinGecko cache TTL.
     */
    public int $uniqueFor = 600;

    /**
     * @param  array<string>|null  $coingeckoIds  Specific IDs to refresh; null → all tokens in DB.
     */
    public function __construct(private readonly ?array $coingeckoIds = null)
    {
        $this->onQueue('default');
    }

    public function handle(CoinGeckoService $coinGecko): void
    {
        $ids = $this->resolveIds();

        if (empty($ids)) {
            Log::info('FetchTokenPricesJob: no tokens to update, skipping');
            return;
        }

        Log::info('FetchTokenPricesJob: fetching prices', ['token_count' => count($ids)]);

        $prices = $coinGecko->refreshPrices($ids);

        if (empty($prices)) {
            Log::warning('FetchTokenPricesJob: CoinGecko returned empty prices, aborting DB update');
            return;
        }

        $updated = 0;
        foreach ($prices as $coinGeckoId => $priceUsd) {
            if ($priceUsd === null) {
                continue;
            }

            $rows = Token::where('coingecko_id', $coinGeckoId)->get();
            foreach ($rows as $token) {
                $token->update([
                    'current_price_usd' => $priceUsd,
                    'price_updated_at'  => now(),
                ]);
                $updated++;
            }
        }

        Log::info('FetchTokenPricesJob: prices updated', [
            'updated_tokens' => $updated,
            'price_count'    => count($prices),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('FetchTokenPricesJob: job failed after all retries', [
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Unique job identifier — only one global price-fetch job at a time.
     * Scoped to the sorted ID list so targeted refreshes don't block full sweeps.
     */
    public function uniqueId(): string
    {
        $ids = $this->resolveIds();
        sort($ids);
        return 'fetch-token-prices:' . md5(implode(',', $ids));
    }

    /**
     * @return array<string>
     */
    private function resolveIds(): array
    {
        if ($this->coingeckoIds !== null) {
            return array_values(array_filter($this->coingeckoIds));
        }

        return Token::whereNotNull('coingecko_id')
            ->pluck('coingecko_id')
            ->unique()
            ->values()
            ->all();
    }
}
