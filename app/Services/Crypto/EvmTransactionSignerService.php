<?php

namespace App\Services\Crypto;

use App\Enums\ChainType;
use App\Exceptions\BlockchainException;
use App\Support\Crypto\RlpEncoder;
use Elliptic\EC;
use kornrunner\Keccak;
use Illuminate\Support\Facades\Log;

/**
 * Signs and broadcasts EVM transactions using a private key stored in env/config.
 * Only used for backend-operated wallets (signer key, not user wallets).
 * User wallets are always signed client-side via MetaMask.
 */
class EvmTransactionSignerService
{
    private const DEFAULT_GAS_LIMIT_NATIVE = 21000;
    private const DEFAULT_GAS_LIMIT_ERC20 = 65000;
    private const DEFAULT_GAS_LIMIT_CONTRACT = 300000;

    public function __construct(
        private readonly EvmRpcService $evmRpc,
    ) {}

    /**
     * Build, sign, and broadcast an EVM transaction.
     *
     * @param  array{to: string, value?: string, data?: string, gasLimit?: string, gasPrice?: string}  $txParams
     * @param  string  $privateKey  Hex private key (with or without 0x prefix)
     * @param  ChainType  $chain
     * @return string  Transaction hash
     * @throws BlockchainException
     */
    public function sendTransaction(array $txParams, string $privateKey, ChainType $chain): string
    {
        try {
            $rpcUrl = $this->rpcUrl($chain);
            $chainId = $this->chainId($chain);
            $fromAddress = $this->privateKeyToAddress($privateKey);

            $nonce = $this->getNonce($fromAddress, $rpcUrl);
            $gasPrice = $txParams['gasPrice'] ?? $this->getGasPrice($rpcUrl);
            $gasLimit = $txParams['gasLimit'] ?? (string) self::DEFAULT_GAS_LIMIT_NATIVE;

            $rawTx = $this->buildAndSign([
                'nonce'    => $nonce,
                'gasPrice' => $gasPrice,
                'gasLimit' => $gasLimit,
                'to'       => $txParams['to'],
                'value'    => $txParams['value'] ?? '0',
                'data'     => $txParams['data'] ?? '',
            ], $privateKey, $chainId);

            $txHash = $this->evmRpc->call($rpcUrl, 'eth_sendRawTransaction', ['0x' . $rawTx]);

            Log::info('Transaction sent', [
                'from'   => $fromAddress,
                'to'     => $txParams['to'],
                'chain'  => $chain->value,
                'txHash' => $txHash,
            ]);

            return (string) $txHash;
        } catch (BlockchainException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new BlockchainException("Failed to send transaction: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Derive the Ethereum address from a private key.
     */
    public function privateKeyToAddress(string $privateKey): string
    {
        $privateKey = ltrim($privateKey, '0x');
        $ec = new EC('secp256k1');
        $keyPair = $ec->keyFromPrivate($privateKey, 'hex');
        $pubKey = $keyPair->getPublic(false, 'hex');

        // Strip 04 prefix, hash uncompressed public key, take last 20 bytes
        $pubBytes = hex2bin(substr($pubKey, 2));
        $hash = Keccak::hash($pubBytes, 256);

        return '0x' . substr($hash, -40);
    }

    /**
     * Sign an arbitrary hash with a private key and return {v, r, s}.
     * Used for off-chain signatures (e.g. ICO purchase authorization).
     */
    public function signHash(string $hashHex, string $privateKey): array
    {
        $privateKey = ltrim($privateKey, '0x');
        $ec = new EC('secp256k1');
        $keyPair = $ec->keyFromPrivate($privateKey, 'hex');

        $sig = $keyPair->sign($hashHex, ['canonical' => true]);

        $r = str_pad($sig->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($sig->s->toString(16), 64, '0', STR_PAD_LEFT);
        $v = $sig->recoveryParam + 27;

        return compact('v', 'r', 's');
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    /**
     * Build a signed EIP-155 transaction (legacy type with replay protection).
     * Returns raw hex string (without 0x prefix).
     */
    private function buildAndSign(array $fields, string $privateKey, int $chainId): string
    {
        $privateKey = ltrim($privateKey, '0x');

        // Build the pre-signing RLP structure with chainId for EIP-155
        $rlpFields = [
            RlpEncoder::intToBytes($fields['nonce']),
            RlpEncoder::intToBytes($fields['gasPrice']),
            RlpEncoder::intToBytes($fields['gasLimit']),
            $fields['to'] ? hex2bin(ltrim($fields['to'], '0x')) : '',
            RlpEncoder::intToBytes($fields['value']),
            $fields['data'] ? hex2bin(ltrim($fields['data'], '0x')) : '',
            RlpEncoder::intToBytes($chainId),
            '',  // v placeholder
            '',  // r placeholder
        ];

        $rlpEncoded = RlpEncoder::encode($rlpFields);
        $hash = Keccak::hash($rlpEncoded, 256);

        // Sign the hash
        $ec = new EC('secp256k1');
        $keyPair = $ec->keyFromPrivate($privateKey, 'hex');
        $sig = $keyPair->sign($hash, ['canonical' => true]);

        $r = str_pad($sig->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($sig->s->toString(16), 64, '0', STR_PAD_LEFT);
        // EIP-155: v = recovery_param + 2 * chainId + 35
        $v = $sig->recoveryParam + 2 * $chainId + 35;

        // Build the signed transaction
        $signedFields = [
            RlpEncoder::intToBytes($fields['nonce']),
            RlpEncoder::intToBytes($fields['gasPrice']),
            RlpEncoder::intToBytes($fields['gasLimit']),
            $fields['to'] ? hex2bin(ltrim($fields['to'], '0x')) : '',
            RlpEncoder::intToBytes($fields['value']),
            $fields['data'] ? hex2bin(ltrim($fields['data'], '0x')) : '',
            RlpEncoder::intToBytes($v),
            hex2bin($r),
            hex2bin($s),
        ];

        return bin2hex(RlpEncoder::encode($signedFields));
    }

    private function getNonce(string $address, string $rpcUrl): int
    {
        $hexNonce = $this->evmRpc->call($rpcUrl, 'eth_getTransactionCount', [$address, 'pending']);

        return hexdec((string) $hexNonce);
    }

    private function getGasPrice(string $rpcUrl): string
    {
        try {
            $hexPrice = $this->evmRpc->call($rpcUrl, 'eth_gasPrice', []);
            $wei = hexdec((string) $hexPrice);

            return (string) $wei;
        } catch (\Throwable) {
            return (string) (20 * 1_000_000_000); // 20 Gwei fallback
        }
    }

    private function rpcUrl(ChainType $chain): string
    {
        return match ($chain) {
            ChainType::ETH     => config('crypto.rpc.eth'),
            ChainType::BNB     => config('crypto.rpc.bnb'),
            ChainType::POLYGON => config('crypto.rpc.polygon'),
            default => throw new BlockchainException("No RPC URL for chain: {$chain->value}"),
        };
    }

    private function chainId(ChainType $chain): int
    {
        return (int) match ($chain) {
            ChainType::ETH     => config('crypto.chains.eth.chain_id', 1),
            ChainType::BNB     => config('crypto.chains.bnb.chain_id', 56),
            ChainType::POLYGON => config('crypto.chains.polygon.chain_id', 137),
            default => throw new BlockchainException("No chain ID for chain: {$chain->value}"),
        };
    }
}
