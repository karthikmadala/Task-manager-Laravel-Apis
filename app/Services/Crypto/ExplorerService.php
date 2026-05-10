<?php

namespace App\Services\Crypto;

use App\Enums\ChainType;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Models\Token;

class ExplorerService
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client(['timeout' => 15]);
    }

    public function getNativeBalance(string $address, ChainType $chain): string
    {
        return (string) ($this->callStandard($chain, [
            'module' => 'account',
            'action' => 'balance',
            'address' => $address,
            'tag' => 'latest',
        ]) ?? '0');
    }

    public function getTokenBalance(string $address, string $contract, ChainType $chain): string
    {
        return (string) ($this->callStandard($chain, [
            'module' => 'account',
            'action' => 'tokenbalance',
            'address' => $address,
            'contractaddress' => $contract,
            'tag' => 'latest',
        ]) ?? '0');
    }

    /**
     * @return array<string, string>
     */
    public function getTokenBalances(string $address, array $contracts, ChainType $chain): array
    {
        $balances = [];

        foreach ($contracts as $contract) {
            $balances[strtolower($contract)] = $this->getTokenBalance($address, $contract, $chain);
        }

        return $balances;
    }

    /**
     * @return array{safe: string, propose: string, fast: string, base_fee: string}|null
     */
    public function getGasOracle(ChainType $chain): ?array
    {
        $result = $this->callStandard($chain, [
            'module' => 'gastracker',
            'action' => 'gasoracle',
        ]);

        if (! is_array($result)) {
            return null;
        }

        $safe = isset($result['SafeGasPrice']) ? (string) $result['SafeGasPrice'] : null;
        $propose = isset($result['ProposeGasPrice']) ? (string) $result['ProposeGasPrice'] : null;
        $fast = isset($result['FastGasPrice']) ? (string) $result['FastGasPrice'] : null;
        $baseFee = isset($result['suggestBaseFee']) ? (string) $result['suggestBaseFee'] : null;

        if ($propose === null && $safe === null) {
            return null;
        }

        $fallback = $propose ?? $safe;

        return [
            'safe' => $safe ?? $fallback,
            'propose' => $fallback,
            'fast' => $fast ?? $fallback,
            'base_fee' => $baseFee ?? '0',
        ];
    }

    public function getSuggestedGasPriceGwei(ChainType $chain): ?string
    {
        return $this->getGasOracle($chain)['propose'] ?? null;
    }

    /**
     * @param  array<string, string>  $txData
     */
    public function estimateEvmGas(array $txData, ChainType $chain): ?string
    {
        $result = $this->callProxy($chain, 'eth_estimateGas', $txData);

        return is_string($result) ? $this->hexToDecimal($result) : null;
    }

    public function getTransactionCount(string $address, ChainType $chain, string $tag = 'pending'): ?string
    {
        $result = $this->callProxy($chain, 'eth_getTransactionCount', [
            'address' => $address,
            'tag' => $tag,
        ]);

        return is_string($result) ? $this->hexToDecimal($result) : null;
    }

    public function getNativeTokenPriceUsd(ChainType $chain): ?float
    {
        $result = $this->callStandard($chain, [
            'module' => 'stats',
            'action' => 'ethprice',
        ]);

        if (! is_array($result)) {
            return null;
        }

        $price = $result['ethusd'] ?? null;

        return is_numeric($price) ? (float) $price : null;
    }

    /**
     * Uses the explorer address-token holdings endpoint, which includes TokenPriceUSD.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAddressTokenHoldings(string $address, ChainType $chain, int $page = 1, int $offset = 100): array
    {
        $result = $this->callStandard($chain, [
            'module' => 'account',
            'action' => 'addresstokenbalance',
            'address' => $address,
            'page' => (string) $page,
            'offset' => (string) $offset,
        ], allowEmptyResult: true);

        return is_array($result) ? array_values($result) : [];
    }

    /**
     * @param  Collection<int, \App\Models\Token>  $tokens
     * @return array<string, float|null>
     */
    public function getUsdPriceMapForTokens(string $address, ChainType $chain, Collection $tokens): array
    {
        $priceMap = [];

        foreach ($tokens as $token) {
            if ($token->isNative()) {
                $priceMap[$this->nativePriceMapKey($chain)] = $this->getNativeTokenPriceUsd($chain);
                continue;
            }

            $priceMap[strtolower((string) $token->contract_address)] = null;
        }

        $holdings = $this->getAddressTokenHoldings($address, $chain);

        foreach ($holdings as $holding) {
            $contract = strtolower((string) ($holding['TokenAddress'] ?? ''));
            $price = $holding['TokenPriceUSD'] ?? null;

            if ($contract === '' || ! array_key_exists($contract, $priceMap)) {
                continue;
            }

            $priceMap[$contract] = is_numeric($price) ? (float) $price : null;
        }

        return $priceMap;
    }

    public function nativePriceMapKey(ChainType $chain): string
    {
        return '__native__:' . $chain->value;
    }

    private function callStandard(ChainType $chain, array $params, bool $allowEmptyResult = false): mixed
    {
        try {
            $body = $this->request($chain, $params);
            $status = (string) ($body['status'] ?? '');
            $result = $body['result'] ?? null;

            if ($status !== '1') {
                if ($allowEmptyResult && ($result === [] || $result === '' || $result === null)) {
                    return [];
                }

                Log::warning('Explorer API error', [
                    'chain' => $chain->value,
                    'action' => $params['action'] ?? 'unknown',
                    'message' => $body['message'] ?? 'unknown',
                    'result' => $result,
                ]);

                return null;
            }

            return $result;
        } catch (GuzzleException $e) {
            Log::error('Explorer API request failed', [
                'chain' => $chain->value,
                'action' => $params['action'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function callProxy(ChainType $chain, string $action, array $params): mixed
    {
        try {
            $body = $this->request($chain, array_merge([
                'module' => 'proxy',
                'action' => $action,
            ], $params));

            if (isset($body['error'])) {
                Log::warning('Explorer proxy API error', [
                    'chain' => $chain->value,
                    'action' => $action,
                    'error' => $body['error'],
                ]);

                return null;
            }

            return $body['result'] ?? null;
        } catch (GuzzleException $e) {
            Log::error('Explorer proxy API request failed', [
                'chain' => $chain->value,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function request(ChainType $chain, array $params): array
    {
        $cfg = $this->chainConfig($chain);
        $query = $params;
        $query['apikey'] = $cfg['key'] ?? '';

        if (($cfg['version'] ?? 'legacy') === 'v2') {
            $query['chainid'] = (string) $cfg['chain_id'];
        }

        $response = $this->http->get($cfg['url'], ['query' => $query]);

        return json_decode((string) $response->getBody(), true) ?? [];
    }

    private function chainConfig(ChainType $chain): array
    {
        if ($chain === ChainType::BTC) {
            throw new \InvalidArgumentException('BTC explorer is handled by BlockCypher.');
        }

        $v2Key = config('crypto.explorer.v2.key');

        // Get chain_id from the native token entry in the tokens table
        $nativeToken = Token::where('chain_type', $chain->value)
            ->whereNull('contract_address')
            ->first();
        $chainId = $nativeToken?->chain_id;

        if ($v2Key && $chainId) {
            return [
                'url' => config('crypto.explorer.v2.url', 'https://api.etherscan.io/v2/api'),
                'key' => $v2Key,
                'chain_id' => $chainId,
                'version' => 'v2',
            ];
        }

        return match ($chain) {
            ChainType::ETH => array_merge(config('crypto.explorer.eth'), ['version' => 'legacy']),
            ChainType::BNB => array_merge(config('crypto.explorer.bnb'), ['version' => 'legacy']),
            ChainType::POLYGON => array_merge(config('crypto.explorer.polygon'), ['version' => 'legacy']),
            default => throw new \InvalidArgumentException('Unsupported chain for explorer requests.'),
        };
    }

    private function hexToDecimal(string $hex): string
    {
        $hex = ltrim(strtolower($hex), '0x');

        if ($hex === '' || $hex === '0') {
            return '0';
        }

        $decimal = '0';
        $length = strlen($hex);

        for ($i = 0; $i < $length; $i++) {
            $decimal = bcadd(bcmul($decimal, '16'), (string) hexdec($hex[$i]));
        }

        return $decimal;
    }
}
