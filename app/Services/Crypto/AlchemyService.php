<?php

namespace App\Services\Crypto;

use App\Enums\ChainType;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class AlchemyService
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client(['timeout' => 15]);
    }

    /**
     * Fetch the native token balance for an EVM address.
     * Returns the balance in wei (as a decimal string).
     */
    public function getNativeBalance(string $address, ChainType $chain): string
    {
        $result = $this->rpc($this->baseUrl($chain), 'eth_getBalance', [$address, 'latest']);
        return $result ? $this->hexToDecimal($result) : '0';
    }

    /**
     * Fetch ERC-20 token balances for a list of contract addresses.
     * Returns [ 'contract_address' => 'decimal_balance_string', ... ]
     * Uses Alchemy's alchemy_getTokenBalances method.
     */
    public function getTokenBalances(string $address, ChainType $chain, array $contractAddresses): array
    {
        if (empty($contractAddresses)) {
            return [];
        }

        // Alchemy supports ETH and Polygon; BNB Chain should use EvmRpcService
        $result = $this->rpc(
            $this->baseUrl($chain),
            'alchemy_getTokenBalances',
            [$address, $contractAddresses]
        );

        if (! $result || ! isset($result['tokenBalances'])) {
            return [];
        }

        $balances = [];
        foreach ($result['tokenBalances'] as $token) {
            $addr = strtolower($token['contractAddress']);
            $hex  = $token['tokenBalance'] ?? '0x0';
            $balances[$addr] = $this->hexToDecimal($hex);
        }

        return $balances;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function baseUrl(ChainType $chain): string
    {
        $key = config('crypto.alchemy.key');

        if (! $key) {
            throw new InvalidArgumentException('ALCHEMY_API_KEY is not configured.');
        }

        return match ($chain) {
            ChainType::ETH     => config('crypto.alchemy.eth') . '/' . $key,
            ChainType::POLYGON => config('crypto.alchemy.polygon') . '/' . $key,
            default => throw new InvalidArgumentException(
                "Alchemy does not support chain: {$chain->value}. Use EvmRpcService instead."
            ),
        };
    }

    private function rpc(string $url, string $method, array $params): mixed
    {
        try {
            $response = $this->http->post($url, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method'  => $method,
                    'params'  => $params,
                    'id'      => 1,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);

            if (isset($body['error'])) {
                Log::warning('Alchemy RPC error', ['method' => $method, 'error' => $body['error']]);
                return null;
            }

            return $body['result'] ?? null;
        } catch (GuzzleException $e) {
            Log::error('Alchemy request failed', ['method' => $method, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Convert a hex string (with or without 0x prefix) to a decimal string.
     * Uses bcmath for arbitrary precision — safe for wei values.
     */
    private function hexToDecimal(string $hex): string
    {
        $hex = ltrim(strtolower($hex), '0x');

        if ($hex === '' || $hex === '0') {
            return '0';
        }

        $dec = '0';
        $len = strlen($hex);

        for ($i = 0; $i < $len; $i++) {
            $dec = bcadd(bcmul($dec, '16'), (string) hexdec($hex[$i]));
        }

        return $dec;
    }
}
