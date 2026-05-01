<?php

namespace App\Services\Crypto;

use App\Enums\ChainType;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Etherscan-compatible block explorer API client.
 * Supports Etherscan (ETH/Sepolia), BscScan (BNB/testnet), PolygonScan (Polygon/Amoy).
 * All three share the same request/response format.
 */
class ExplorerService
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client(['timeout' => 15]);
    }

    /**
     * Fetch native token balance. Returns decimal string in wei (or satoshi for BTC).
     */
    public function getNativeBalance(string $address, ChainType $chain): string
    {
        return $this->call($chain, [
            'module'  => 'account',
            'action'  => 'balance',
            'address' => $address,
            'tag'     => 'latest',
        ]) ?? '0';
    }

    /**
     * Fetch a single ERC-20 token balance. Returns decimal string in token's smallest unit.
     */
    public function getTokenBalance(string $address, string $contract, ChainType $chain): string
    {
        return $this->call($chain, [
            'module'          => 'account',
            'action'          => 'tokenbalance',
            'address'         => $address,
            'contractaddress' => $contract,
            'tag'             => 'latest',
        ]) ?? '0';
    }

    /**
     * Fetch multiple ERC-20 token balances.
     * Returns [ 'contract_address' => 'decimal_balance', ... ]
     */
    public function getTokenBalances(string $address, array $contracts, ChainType $chain): array
    {
        $balances = [];

        foreach ($contracts as $contract) {
            $balances[strtolower($contract)] = $this->getTokenBalance($address, $contract, $chain);
        }

        return $balances;
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function call(ChainType $chain, array $params): ?string
    {
        $cfg = $this->chainConfig($chain);
        $params['apikey'] = $cfg['key'] ?? '';

        try {
            $response = $this->http->get($cfg['url'], ['query' => $params]);
            $body = json_decode((string) $response->getBody(), true);

            if (($body['status'] ?? '') !== '1') {
                Log::warning('Explorer API error', [
                    'chain'   => $chain->value,
                    'action'  => $params['action'],
                    'message' => $body['message'] ?? 'unknown',
                ]);
                return null;
            }

            return $body['result'];
        } catch (GuzzleException $e) {
            Log::error('Explorer API request failed', [
                'chain' => $chain->value,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function chainConfig(ChainType $chain): array
    {
        return match ($chain) {
            ChainType::ETH     => config('crypto.explorer.eth'),
            ChainType::BNB     => config('crypto.explorer.bnb'),
            ChainType::POLYGON => config('crypto.explorer.polygon'),
            ChainType::BTC     => throw new \InvalidArgumentException('BTC explorer is handled by BlockCypher.'),
        };
    }
}
