<?php

namespace App\Services\Crypto;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CoinGeckoService
{
    private Client $http;

    public function __construct()
    {
        $headers = ['Accept' => 'application/json'];

        if ($key = config('crypto.coingecko.api_key')) {
            $headers['x-cg-demo-api-key'] = $key;
        }

        $this->http = new Client([
            'base_uri' => config('crypto.coingecko.base_url'),
            'timeout'  => 10,
            'headers'  => $headers,
        ]);
    }

    /**
     * Fetch USD prices for multiple CoinGecko IDs in one call.
     * Returns [ 'ethereum' => 3000.50, 'bitcoin' => 65000.00, ... ]
     * Caches in Redis for the configured TTL (default 300s).
     */
    public function getPrices(array $coinGeckoIds): array
    {
        if (empty($coinGeckoIds)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter($coinGeckoIds)));
        sort($ids);
        $cacheKey = 'coingecko:prices:' . md5(implode(',', $ids));
        $ttl = (int) config('crypto.coingecko.cache_ttl', 300);

        return Cache::remember($cacheKey, $ttl, function () use ($ids) {
            return $this->fetchPrices($ids);
        });
    }

    /**
     * Fetch a single token price. Returns null on failure.
     */
    public function getPrice(string $coinGeckoId): ?float
    {
        return $this->getPrices([$coinGeckoId])[$coinGeckoId] ?? null;
    }

    /**
     * Bust the price cache and fetch fresh data.
     */
    public function refreshPrices(array $coinGeckoIds): array
    {
        $ids = array_values(array_unique(array_filter($coinGeckoIds)));
        sort($ids);
        $cacheKey = 'coingecko:prices:' . md5(implode(',', $ids));

        Cache::forget($cacheKey);
        return $this->getPrices($ids);
    }

    private function fetchPrices(array $ids): array
    {
        try {
            $response = $this->http->get('/simple/price', [
                'query' => [
                    'ids'           => implode(',', $ids),
                    'vs_currencies' => 'usd',
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);

            return collect($data)
                ->mapWithKeys(fn ($v, $k) => [$k => isset($v['usd']) ? (float) $v['usd'] : null])
                ->toArray();
        } catch (GuzzleException $e) {
            Log::error('CoinGecko price fetch failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
