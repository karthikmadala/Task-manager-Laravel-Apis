<?php

namespace App\Services\Crypto;

use App\Enums\ChainType;
use App\Exceptions\BlockchainException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Bridge between Laravel backend and the Node.js microservice (node_api_base).
 *
 * All requests include the shared HMAC-SHA256 secret via X-Service-Secret header.
 * The Node.js service handles private-key-dependent operations (signing, transfers,
 * staking, ICO) using keystore files — so Laravel never holds raw private keys.
 *
 * The Laravel-side EvmRpcService covers read-only RPC calls (balances, gas estimates,
 * tx receipts). This service covers write operations that require signing.
 */
class BlockchainNodeService
{
    private Client $http;
    private string $baseUrl;
    private string $secret;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('crypto.node_service.url', 'http://localhost:5000'), '/');
        $this->secret  = config('crypto.node_service.secret', '');
        $this->http    = new Client([
            'timeout' => config('crypto.node_service.timeout', 30),
        ]);
    }

    /**
     * Map Laravel ChainType to the node_api_base chain string.
     *
     * @throws BlockchainException
     */
    public function nodeChain(ChainType $chain): string
    {
        return match ($chain) {
            ChainType::ETH     => 'ETH',
            ChainType::BNB     => 'BNB',
            ChainType::POLYGON => 'MATIC',
            ChainType::BTC     => throw new BlockchainException('BTC not supported by node service'),
        };
    }

    // ─── Signing & Key Management ─────────────────────────────────────────────

    /**
     * Reveal a private key from an encrypted keystore file.
     * The keystore lives on disk at KEY_DIRECTORY — Laravel never stores raw keys.
     *
     * @param  string  $address   Ethereum address (without 0x)
     * @param  string  $password  Decryption password
     * @return string  Raw private key (hex, no 0x prefix)
     * @throws BlockchainException
     */
    public function decryptKeystoreKey(string $address, string $password): string
    {
        $response = $this->post('/getKey', [
            'address' => $address,
            'string'  => $password,
        ]);

        return $response['key'];
    }

    /**
     * Create a backend-signed authorization signature for ICO purchases.
     * The signer wallet is loaded from keystore by the node service.
     *
     * @param  ChainType  $chain
     * @param  int        $index          Payment index from ICO contract
     * @param  string     $beneficiary    Wallet receiving tokens
     * @param  string     $caller         ICO contract address
     * @param  string     $cryptoValue    Amount in smallest unit (wei/satoshi)
     * @return array{v, r, s}  EIP-191 signature components
     * @throws BlockchainException
     */
    public function createSign(
        ChainType $chain,
        int $index,
        string $beneficiary,
        string $caller,
        string $cryptoValue,
    ): array {
        $response = $this->post('/createSign', [
            'chain'       => $this->nodeChain($chain),
            'index'       => $index,
            'address'     => $beneficiary,
            'caller'      => $caller,
            'cryptoValue' => $cryptoValue,
        ]);

        $sig = $response['signature'];

        return [
            'v' => $sig['v'],
            'r' => $sig['r'],
            's' => $sig['s'],
        ];
    }

    // ─── Transfers ─────────────────────────────────────────────────────────────

    /**
     * Transfer ERC-20 tokens using the keystore wallet identified by $key.
     * The node service decrypts the keystore, signs, and broadcasts the transaction.
     *
     * @param  ChainType  $chain
     * @param  string     $key           Keystore address (or raw private key if keystore not used)
     * @param  string     $contractAddress  ERC-20 token contract
     * @param  string     $to            Recipient address
     * @param  float      $amount        Token amount (human-readable)
     * @param  int        $decimals      Token decimals
     * @return string     Transaction hash
     * @throws BlockchainException
     */
    public function transferErc20(
        ChainType $chain,
        string $key,
        string $contractAddress,
        string $to,
        float $amount,
        int $decimals,
    ): string {
        $response = $this->post('/transfer', [
            'chain'          => $this->nodeChain($chain),
            'key'            => $key,
            'contract_address' => $contractAddress,
            'to'             => $to,
            'amount'         => $amount,
            'decimal'        => $decimals,
        ]);

        return $response['txHash'];
    }

    /**
     * Transfer native tokens (ETH/BNB/MATIC) using the keystore wallet.
     *
     * @param  ChainType  $chain
     * @param  string     $key           Raw private key or keystore address
     * @param  string     $to            Recipient address
     * @param  float      $value         Amount in native token units (ETH/BNB/MATIC)
     * @return string     Transaction hash
     * @throws BlockchainException
     */
    public function transferNative(
        ChainType $chain,
        string $key,
        string $to,
        float $value,
    ): string {
        $response = $this->post('/eth_transfer', [
            'chain' => $this->nodeChain($chain),
            'key'   => $key,
            'to'    => $to,
            'value' => $value,
        ]);

        return $response['txHash'];
    }

    // ─── Staking ──────────────────────────────────────────────────────────────

    /**
     * Stake tokens through the staking contract using the keystore wallet.
     *
     * @param  ChainType  $chain
     * @param  string     $key           Raw private key
     * @param  float      $amount        Stake amount (human-readable)
     * @param  int        $level         Staking plan level
     * @return string     Transaction hash
     * @throws BlockchainException
     */
    public function stake(
        ChainType $chain,
        string $key,
        float $amount,
        int $level,
    ): string {
        $response = $this->post('/staking', [
            'chain'  => $this->nodeChain($chain),
            'key'    => $key,
            'amount' => $amount,
            'level'  => $level,
        ]);

        return $response['txHash'];
    }

    /**
     * Withdraw or emergency-withdraw from the staking contract.
     *
     * @param  ChainType  $chain
     * @param  string     $key           Raw private key
     * @param  int        $level         Staking plan level
     * @param  string     $type          'withdraw' or 'emergency'
     * @return string     Transaction hash
     * @throws BlockchainException
     */
    public function withdraw(
        ChainType $chain,
        string $key,
        int $level,
        string $type = 'withdraw',
    ): string {
        $response = $this->post('/e-withdraw', [
            'chain'  => $this->nodeChain($chain),
            'key'    => $key,
            'level'  => $level,
            'type'   => $type,
        ]);

        return $response['txHash'];
    }

    // ─── ICO ─────────────────────────────────────────────────────────────────

    /**
     * Purchase ICO tokens using the keystore wallet.
     *
     * @param  ChainType  $chain
     * @param  string     $key           Raw private key
     * @param  string     $beneficiary   Wallet receiving tokens
     * @param  int        $paymentIndex  Payment index from ICO contract
     * @param  string     $amount        Native token amount to pay (wei string)
     * @param  array{v,r,s} $signature   Off-chain authorization signature
     * @return string     Transaction hash
     * @throws BlockchainException
     */
    public function buyIcoTokens(
        ChainType $chain,
        string $key,
        string $beneficiary,
        int $paymentIndex,
        string $amount,
        array $signature,
    ): string {
        $response = $this->post('/buyTokens', [
            'chain'         => $this->nodeChain($chain),
            'key'           => $key,
            'address'       => $beneficiary,
            'paymentindex'  => $paymentIndex,
            'amount'        => $amount,
            'signature'     => $signature,
        ]);

        return $response['txHash'];
    }

    // ─── Read Operations (delegated to node for consistency) ──────────────────

    /**
     * Get ERC-20 token balance.
     *
     * @param  ChainType  $chain
     * @param  string     $contractAddress
     * @param  string     $address        Wallet address
     * @return float      Balance (human-readable — decimals handled by node)
     * @throws BlockchainException
     */
    public function getErc20Balance(
        ChainType $chain,
        string $contractAddress,
        string $address,
    ): float {
        $response = $this->post('/balance', [
            'chain'          => $this->nodeChain($chain),
            'contract_address' => $contractAddress,
            'address'        => $address,
        ]);

        return (float) $response['balance'];
    }

    /**
     * Get native token balance.
     *
     * @param  ChainType  $chain
     * @param  string     $address
     * @return float      Balance in ETH/BNB/MATIC
     * @throws BlockchainException
     */
    public function getNativeBalance(ChainType $chain, string $address): float
    {
        $response = $this->post('/native_balance', [
            'chain'   => $this->nodeChain($chain),
            'address' => $address,
        ]);

        return (float) $response['balance'];
    }

    /**
     * Get transaction receipt.
     */
    public function getReceipt(ChainType $chain, string $txHash): array
    {
        $response = $this->post('/getReceipt', [
            'chain' => $this->nodeChain($chain),
            'hash'  => $txHash,
        ]);

        return $response['transactionReceipt'];
    }

    /**
     * Get ERC-20 token metadata.
     */
    public function getTokenDetails(ChainType $chain, string $contractAddress): array
    {
        $response = $this->get('/tokendetails', [
            'chain'          => $this->nodeChain($chain),
            'contractAddress' => $contractAddress,
        ]);

        return $response;
    }

    /**
     * Get current gas price for a chain.
     *
     * @return array{gasPrice: string, maxFeePerGas: string}  Values in wei
     */
    public function getGasPrice(ChainType $chain): array
    {
        $response = $this->post('/getGasPrice', [
            'chain' => $this->nodeChain($chain),
        ]);

        return [
            'gasPrice'     => $response['gasPrice'],
            'maxFeePerGas' => $response['maxFeePerGas'],
        ];
    }

    /**
     * Broadcast a raw signed transaction.
     *
     * @param  string  $rawTx  Signed transaction hex (with 0x prefix)
     * @return string  Transaction hash
     * @throws BlockchainException
     */
    public function broadcastRawTransaction(ChainType $chain, string $rawTx): string
    {
        $response = $this->post('/broadcastRawTx', [
            'chain'  => $this->nodeChain($chain),
            'raw_tx' => $rawTx,
        ]);

        return $response['txHash'];
    }

    /**
     * Get the next pending nonce for an address.
     *
     * @throws BlockchainException
     */
    public function getNonce(ChainType $chain, string $address): int
    {
        $response = $this->post('/getNonce', [
            'chain'   => $this->nodeChain($chain),
            'address' => $address,
        ]);

        return (int) $response['nonce'];
    }

    /**
     * Execute a read-only eth_call against a contract.
     *
     * @param  string  $to    Contract address
     * @param  string  $data  ABI-encoded call data (0x-prefixed hex)
     * @return string  Raw hex result
     * @throws BlockchainException
     */
    public function ethCall(ChainType $chain, string $to, string $data): string
    {
        $response = $this->post('/eth_call', [
            'chain' => $this->nodeChain($chain),
            'to'    => $to,
            'data'  => $data,
        ]);

        return (string) $response['result'];
    }

    /**
     * Estimate gas for a transaction.
     *
     * @return string  Gas limit as decimal string
     * @throws BlockchainException
     */
    public function estimateGas(
        ChainType $chain,
        string $to,
        ?string $from = null,
        ?string $data = null,
        ?string $value = null,
    ): string {
        $body = ['chain' => $this->nodeChain($chain), 'to' => $to];

        if ($from !== null)  $body['from']  = $from;
        if ($data !== null)  $body['data']  = $data;
        if ($value !== null) $body['value'] = $value;

        $response = $this->post('/eth_estimateGas', $body);

        return (string) $response['gas_limit'];
    }

    /**
     * Fetch USD prices for a list of CoinGecko IDs.
     * Returns [ 'ethereum' => 3200.50, 'bitcoin' => 65000.0, ... ]
     *
     * @param  string[]  $coinGeckoIds
     * @return array<string, float|null>
     * @throws BlockchainException
     */
    public function getPrices(array $coinGeckoIds): array
    {
        $response = $this->post('/prices', ['ids' => array_values($coinGeckoIds)]);
        return $response['prices'] ?? [];
    }

    /**
     * Fetch native token USD price (ETH/BNB/MATIC) from the explorer stats endpoint.
     *
     * @throws BlockchainException
     */
    public function getNativePrice(ChainType $chain): ?float
    {
        try {
            $response = $this->get('/native-price/' . $this->nodeChain($chain), []);
            $price = $response['price_usd'] ?? null;
            return $price !== null ? (float) $price : null;
        } catch (BlockchainException) {
            return null;
        }
    }

    /**
     * Fetch BTC balance via BlockCypher.
     * Returns satoshi-denominated balance strings.
     *
     * @return array{ balance: string, unconfirmed_balance: string, final_balance: string }
     * @throws BlockchainException
     */
    public function getBtcBalance(string $address): array
    {
        return $this->post('/btc-balance', ['address' => $address]);
    }

    /**
     * Fetch Etherscan-style gas oracle for a chain.
     * Returns { safe, propose, fast, base_fee } in Gwei, or null if unavailable.
     *
     * @return array{safe: string, propose: string, fast: string, base_fee: string}|null
     */
    public function getGasOracle(ChainType $chain): ?array
    {
        try {
            return $this->get('/gas-oracle/' . $this->nodeChain($chain), []);
        } catch (BlockchainException) {
            return null;
        }
    }

    /**
     * Fetch native + ERC-20 balances via the node's fallback chain
     * (Alchemy → Explorer → RPC) in a single call.
     *
     * @param  string[]  $contracts  ERC-20 contract addresses to query
     * @return array{
     *   native_balance: string,
     *   native_provider: string,
     *   token_balances: array<string, string>,
     *   token_provider: string
     * }
     * @throws BlockchainException
     */
    public function fetchPortfolioBalances(
        ChainType $chain,
        string $address,
        array $contracts = [],
    ): array {
        return $this->post('/portfolio/balances', [
            'chain'     => $this->nodeChain($chain),
            'address'   => $address,
            'contracts' => $contracts,
        ]);
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    /**
     * POST to a node_api_base endpoint.
     *
     * @throws BlockchainException
     */
    private function post(string $path, array $body): array
    {
        try {
            $res = $this->http->request('POST', $this->baseUrl . $path, [
                'headers' => [
                    'Content-Type'        => 'application/json',
                    'X-Service-Secret'    => $this->secret,
                ],
                'json' => $body,
            ]);

            $data = $this->parseResponse($res, $path);

            Log::debug('[BlockchainNodeService] POST ' . $path, ['body' => $body, 'response' => $data]);

            return $data;
        } catch (GuzzleException $e) {
            throw new BlockchainException(
                "Node service error on {$path}: {$e->getMessage()}",
                0, $e
            );
        }
    }

    /**
     * GET to a node_api_base endpoint.
     *
     * @throws BlockchainException
     */
    private function get(string $path, array $query): array
    {
        try {
            $res = $this->http->request('GET', $this->baseUrl . $path, [
                'headers' => [
                    'X-Service-Secret' => $this->secret,
                ],
                'query' => $query,
            ]);

            $data = $this->parseResponse($res, $path);

            Log::debug('[BlockchainNodeService] GET ' . $path, ['query' => $query, 'response' => $data]);

            return $data;
        } catch (GuzzleException $e) {
            throw new BlockchainException(
                "Node service error on {$path}: {$e->getMessage()}",
                0, $e
            );
        }
    }

    /**
     * Unwrap the node_api_base response envelope.
     *
     * @throws BlockchainException
     */
    private function parseResponse($response, string $path): array
    {
        $body = json_decode((string) $response->getBody(), true);

        if (! ($body['success'] ?? false)) {
            throw new BlockchainException(
                'Node service returned error: ' . ($body['message'] ?? 'unknown'),
            );
        }

        return $body['data'] ?? [];
    }
}
