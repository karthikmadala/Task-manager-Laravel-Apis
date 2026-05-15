<?php

namespace App\Services\Crypto;

use App\Enums\ChainType;
use App\Exceptions\BlockchainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Read-only blockchain queries: token metadata, balances, receipts, gas prices.
 * Delegates all calls to BlockchainNodeService (node_api_base microservice).
 */
class BlockchainInfoService
{
    public function __construct(
        private readonly BlockchainNodeService $node,
    ) {}

    /**
     * Fetch ERC-20 token metadata (name, symbol, decimals, totalSupply).
     */
    public function getTokenDetails(string $contractAddress, ChainType $chain): array
    {
        $cacheKey = "token:details:{$chain->value}:" . strtolower($contractAddress);

        return Cache::remember($cacheKey, 3600, function () use ($contractAddress, $chain) {
            return $this->node->getTokenDetails($chain, $contractAddress);
        });
    }

    /**
     * Get ERC-20 token balance for an address.
     */
    public function getErc20Balance(string $walletAddress, string $contractAddress, ChainType $chain): array
    {
        $data = $this->node->getErc20Balance($chain, $contractAddress, $walletAddress);

        return [
            'address'  => strtolower($walletAddress),
            'contract' => strtolower($contractAddress),
            'chain'    => $chain->value,
            'balance'  => $data,
        ];
    }

    /**
     * Get native token balance (ETH/BNB/MATIC) for an address.
     */
    public function getNativeBalance(string $address, ChainType $chain): array
    {
        $balance = $this->node->getNativeBalance($chain, $address);

        return [
            'address' => strtolower($address),
            'chain'   => $chain->value,
            'symbol'  => $chain->nativeSymbol(),
            'balance' => $balance,
        ];
    }

    /**
     * Fetch a transaction receipt by hash.
     */
    public function getTransactionReceipt(string $txHash, ChainType $chain): ?array
    {
        try {
            return $this->node->getReceipt($chain, $txHash);
        } catch (BlockchainException $e) {
            Log::warning('Transaction receipt fetch failed', [
                'txHash' => $txHash,
                'chain'  => $chain->value,
                'error'  => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get current gas price / fee data for a chain.
     */
    public function getGasPrice(ChainType $chain): array
    {
        $cacheKey = "gas:price:{$chain->value}";

        return Cache::remember($cacheKey, 15, function () use ($chain) {
            try {
                $data = $this->node->getGasPrice($chain);

                $gasPriceWei = $data['gasPrice'] ?? null;
                $gasPriceGwei = $gasPriceWei !== null
                    ? bcdiv((string) $gasPriceWei, '1000000000', 9)
                    : null;

                return [
                    'chain'           => $chain->value,
                    'gas_price_wei'   => $gasPriceWei,
                    'gas_price_gwei'  => $gasPriceGwei,
                    'max_fee_per_gas' => $data['maxFeePerGas'] ?? null,
                ];
            } catch (BlockchainException $e) {
                Log::warning('Gas price fetch failed', ['chain' => $chain->value, 'error' => $e->getMessage()]);

                return [
                    'chain'           => $chain->value,
                    'gas_price_wei'   => null,
                    'gas_price_gwei'  => null,
                    'max_fee_per_gas' => null,
                    'error'           => 'Gas price temporarily unavailable',
                ];
            }
        });
    }

    /**
     * Get the next pending transaction nonce for an address.
     */
    public function getNonce(string $address, ChainType $chain): int
    {
        return $this->node->getNonce($chain, $address);
    }
}
