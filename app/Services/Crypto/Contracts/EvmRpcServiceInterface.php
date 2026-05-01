<?php

namespace App\Services\Crypto\Contracts;

use App\Enums\ChainType;

interface EvmRpcServiceInterface
{
    /**
     * Fetch native token balance in wei (decimal string).
     * Pass $rpcUrl to override the chain's configured endpoint.
     */
    public function getNativeBalance(string $address, ChainType $chain, ?string $rpcUrl = null): string;

    /**
     * Fetch a single ERC-20 token balance in the token's smallest unit (decimal string).
     */
    public function getErc20Balance(
        string $walletAddress,
        string $contractAddress,
        ChainType $chain,
        ?string $rpcUrl = null,
    ): string;

    /**
     * Fetch multiple ERC-20 balances via a single batch JSON-RPC call.
     * Falls back to sequential calls if the node rejects batch requests.
     *
     * @param  string[]  $contractAddresses
     * @return array<string, string>  keyed by lowercase contract address
     */
    public function getErc20Balances(
        string $walletAddress,
        array $contractAddresses,
        ChainType $chain,
        ?string $rpcUrl = null,
    ): array;

    /**
     * Convert a raw integer string (wei/satoshi) to its human-readable decimal form.
     */
    public function toDecimalUnits(string $rawAmount, int $decimals): string;

    /**
     * Execute a raw JSON-RPC call against any EVM node URL.
     * Returns the raw 'result' value from the node response.
     *
     * @throws \RuntimeException on network failure, RPC error, or empty result
     */
    public function call(string $rpcUrl, string $method, array $params): mixed;
}
