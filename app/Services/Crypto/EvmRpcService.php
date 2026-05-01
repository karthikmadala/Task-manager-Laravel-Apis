<?php

namespace App\Services\Crypto;

use App\Enums\ChainType;
use App\Services\Crypto\Contracts\EvmRpcServiceInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class EvmRpcService implements EvmRpcServiceInterface
{
    private const MAX_RETRIES = 3;
    private const TIMEOUT_SECONDS = 15;
    private const RETRYABLE_STATUS = [429, 500, 502, 503, 504];

    // balanceOf(address) — keccak256("balanceOf(address)")[0..3]
    private const BALANCE_OF_SELECTOR = '0x70a08231';

    public function __construct(private readonly ClientInterface $http) {}

    /**
     * Build a Guzzle client equipped with exponential-backoff retry middleware.
     * Intended to be called from AppServiceProvider when binding this service.
     */
    public static function buildRetryClient(
        int $maxRetries = self::MAX_RETRIES,
        int $timeoutSeconds = self::TIMEOUT_SECONDS,
    ): Client {
        $stack = HandlerStack::create();

        $stack->push(Middleware::retry(
            static function (
                int $retries,
                mixed $_,
                ?ResponseInterface $response,
                ?\Throwable $exception,
            ) use ($maxRetries): bool {
                if ($retries >= $maxRetries) {
                    return false;
                }
                if ($exception instanceof ConnectException) {
                    return true;
                }
                return $response !== null
                    && in_array($response->getStatusCode(), self::RETRYABLE_STATUS, true);
            },
            // Exponential backoff: 200 ms → 400 ms → 800 ms
            static fn(int $retries): int => (int) (200 * (2 ** $retries)),
        ));

        return new Client(['timeout' => $timeoutSeconds, 'handler' => $stack]);
    }

    // ─── Interface ───────────────────────────────────────────────────────────

    public function getNativeBalance(string $address, ChainType $chain, ?string $rpcUrl = null): string
    {
        $url = $rpcUrl ?? $this->configUrl($chain);
        $result = $this->rpc($url, 'eth_getBalance', [$address, 'latest']);

        return $result !== null ? $this->hexToDecimal((string) $result) : '0';
    }

    public function getErc20Balance(
        string $walletAddress,
        string $contractAddress,
        ChainType $chain,
        ?string $rpcUrl = null,
    ): string {
        $url = $rpcUrl ?? $this->configUrl($chain);
        $data = self::BALANCE_OF_SELECTOR . $this->padAddress($walletAddress);

        $result = $this->rpc($url, 'eth_call', [
            ['to' => $contractAddress, 'data' => $data],
            'latest',
        ]);

        return $result !== null ? $this->hexToDecimal((string) $result) : '0';
    }

    public function getErc20Balances(
        string $walletAddress,
        array $contractAddresses,
        ChainType $chain,
        ?string $rpcUrl = null,
    ): array {
        if (empty($contractAddresses)) {
            return [];
        }

        $url = $rpcUrl ?? $this->configUrl($chain);
        $paddedWallet = $this->padAddress($walletAddress);
        $contracts = array_values($contractAddresses);

        $batchRequests = array_map(
            static fn(string $contract, int $i) => [
                'jsonrpc' => '2.0',
                'method'  => 'eth_call',
                'params'  => [
                    ['to' => $contract, 'data' => self::BALANCE_OF_SELECTOR . $paddedWallet],
                    'latest',
                ],
                'id' => $i + 1,
            ],
            $contracts,
            array_keys($contracts),
        );

        try {
            $responses = $this->batchRpc($url, $batchRequests);

            return $this->indexBatchBalances($responses, $contracts);
        } catch (RuntimeException $e) {
            Log::warning('EVM batch RPC failed, falling back to sequential calls', [
                'chain' => $chain->value,
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return $this->getErc20BalancesSequential($walletAddress, $contracts, $url);
        }
    }

    public function toDecimalUnits(string $rawAmount, int $decimals): string
    {
        if ($rawAmount === '0') {
            return '0';
        }

        return bcdiv($rawAmount, bcpow('10', (string) $decimals), $decimals);
    }

    /**
     * Execute a raw JSON-RPC call against any EVM endpoint.
     *
     * @throws RuntimeException if the call fails or the node returns an error
     */
    public function call(string $rpcUrl, string $method, array $params): mixed
    {
        $result = $this->rpc($rpcUrl, $method, $params);

        if ($result === null) {
            throw new RuntimeException(
                "JSON-RPC '{$method}' returned no result from {$rpcUrl}."
            );
        }

        return $result;
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    /**
     * Execute a single JSON-RPC call. Returns the 'result' field or null on error.
     */
    private function rpc(string $url, string $method, array $params): mixed
    {
        try {
            $response = $this->http->request('POST', $url, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method'  => $method,
                    'params'  => $params,
                    'id'      => 1,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);

            if (isset($body['error'])) {
                Log::warning('EVM RPC error', [
                    'method' => $method,
                    'url'    => $url,
                    'error'  => $body['error'],
                ]);

                return null;
            }

            return $body['result'] ?? null;
        } catch (GuzzleException $e) {
            Log::error('EVM RPC request failed', [
                'method' => $method,
                'url'    => $url,
                'error'  => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send a batch of JSON-RPC requests in a single HTTP call.
     * Batch responses may arrive out of order — callers must match by 'id'.
     *
     * @param  array<array{jsonrpc:string,method:string,params:array,id:int}>  $requests
     * @return array<array{id:int,result?:mixed,error?:mixed}>
     * @throws RuntimeException on HTTP or JSON parse failure
     */
    private function batchRpc(string $url, array $requests): array
    {
        try {
            $response = $this->http->request('POST', $url, ['json' => $requests]);
            $body = json_decode((string) $response->getBody(), true);

            if (! is_array($body)) {
                throw new RuntimeException(
                    'EVM node returned a non-array response for a batch request — node may not support batching.'
                );
            }

            return $body;
        } catch (GuzzleException $e) {
            throw new RuntimeException("EVM batch RPC failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Map batch response objects back to [ lowercase_contract => decimal_balance ].
     *
     * @param  string[]  $contracts  ordered list matching request IDs (1-indexed)
     */
    private function indexBatchBalances(array $responses, array $contracts): array
    {
        $balances = [];

        foreach ($responses as $response) {
            $index = ($response['id'] ?? 1) - 1;
            $contract = strtolower($contracts[$index] ?? '');

            if ($contract === '') {
                continue;
            }

            if (isset($response['error'])) {
                Log::warning('EVM batch RPC item error', [
                    'contract' => $contract,
                    'error'    => $response['error'],
                ]);
            }

            $balances[$contract] = $this->hexToDecimal((string) ($response['result'] ?? '0x0'));
        }

        return $balances;
    }

    /**
     * Sequential fallback when a node rejects batch requests.
     *
     * @param  string[]  $contracts
     * @return array<string, string>
     */
    private function getErc20BalancesSequential(
        string $walletAddress,
        array $contracts,
        string $rpcUrl,
    ): array {
        $paddedWallet = $this->padAddress($walletAddress);
        $balances = [];

        foreach ($contracts as $contract) {
            $result = $this->rpc($rpcUrl, 'eth_call', [
                ['to' => $contract, 'data' => self::BALANCE_OF_SELECTOR . $paddedWallet],
                'latest',
            ]);

            $balances[strtolower($contract)] = $result !== null
                ? $this->hexToDecimal((string) $result)
                : '0';
        }

        return $balances;
    }

    private function configUrl(ChainType $chain): string
    {
        return match ($chain) {
            ChainType::ETH     => config('crypto.rpc.eth'),
            ChainType::BNB     => config('crypto.rpc.bnb'),
            ChainType::POLYGON => config('crypto.rpc.polygon'),
            ChainType::BTC     => throw new \InvalidArgumentException(
                'BTC is not EVM-compatible. Use BlockCypherService.'
            ),
        };
    }

    /**
     * Pad a checksummed/lowercase address to 32 bytes for ABI encoding.
     */
    private function padAddress(string $address): string
    {
        return str_pad(ltrim(strtolower($address), '0x'), 64, '0', STR_PAD_LEFT);
    }

    /**
     * Convert a hex string (with or without 0x prefix) to a decimal string.
     * Uses bcmath — safe for 256-bit wei values.
     */
    private function hexToDecimal(string $hex): string
    {
        $hex = ltrim(strtolower($hex), '0x');

        if ($hex === '' || $hex === '0') {
            return '0';
        }

        $dec = '0';
        $len = \strlen($hex);

        for ($i = 0; $i < $len; $i++) {
            $dec = bcadd(bcmul($dec, '16'), (string) hexdec($hex[$i]));
        }

        return $dec;
    }
}
