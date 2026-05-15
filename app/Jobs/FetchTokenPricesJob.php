<?php

namespace App\Jobs;

use App\Enums\ChainType;
use App\Models\Token;
use App\Services\Crypto\ExplorerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchTokenPricesJob implements ShouldQueue
{
    use Queueable;

    public string $queue = 'default';
    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public int $uniqueFor = 600;

    public function __construct(private readonly ?array $coingeckoIds = null)
    {
        $this->onQueue('default');
    }

    public function handle(ExplorerService $explorer): void
    {
        $nativeChains = [ChainType::ETH, ChainType::BNB, ChainType::POLYGON];
        $updated = 0;

        foreach ($nativeChains as $chain) {
            try {
                $price = $explorer->getNativeTokenPriceUsd($chain);

                if ($price === null) {
                    continue;
                }

                $token = Token::where('chain_type', $chain->value)
                    ->whereNull('contract_address')
                    ->first();

                if (! $token) {
                    continue;
                }

                $token->update([
                    'current_price_usd' => $price,
                    'price_updated_at' => now(),
                ]);
                $updated++;
            } catch (\Throwable $e) {
                Log::warning('FetchTokenPricesJob: explorer price refresh failed', [
                    'chain' => $chain->value,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('FetchTokenPricesJob: explorer native prices updated', [
            'updated_tokens' => $updated,
            'note' => 'ERC-20 prices are resolved live from explorer address holdings during portfolio sync.',
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('FetchTokenPricesJob: job failed after all retries', [
            'error' => $e->getMessage(),
        ]);
    }

    public function uniqueId(): string
    {
        $ids = $this->coingeckoIds ?? [];
        sort($ids);

        return 'fetch-token-prices:' . md5(implode(',', $ids));
    }
}
