<?php

namespace App\Services;

use App\Enums\ChainType;
use App\Enums\TransactionStatus;
use App\Exceptions\NonceConflictException;
use App\Exceptions\TransactionBroadcastFailedException;
use App\Models\Transaction;
use App\Services\Crypto\BlockchainNodeService;
use App\Support\Crypto\RlpEncoder;
use Elliptic\EC;
use kornrunner\Keccak;
use Illuminate\Support\Facades\Log;

class TransactionBroadcastService
{
    public function __construct(
        private readonly BlockchainNodeService $nodeService,
    ) {}

    public function broadcastSignedTransaction(string $rawTx, ChainType $chain): string
    {
        try {
            $txHash = $this->nodeService->broadcastRawTransaction($chain, $rawTx);

            Log::info('Transaction broadcast successfully', [
                'tx_hash' => $txHash,
                'chain'   => $chain->value,
            ]);

            return $txHash;
        } catch (\Exception $e) {
            Log::error('Transaction broadcast failed', [
                'error' => $e->getMessage(),
                'chain' => $chain->value,
            ]);
            throw new TransactionBroadcastFailedException('Failed to broadcast transaction: ' . $e->getMessage());
        }
    }

    public function broadcastClientSigned(Transaction $transaction, string $signature): Transaction
    {
        if ($transaction->status !== TransactionStatus::PENDING) {
            throw new TransactionBroadcastFailedException('Transaction must be in PENDING status to broadcast');
        }

        try {
            $chain  = $transaction->chain_type;
            $txHash = $this->broadcastSignedTransaction($signature, $chain);

            $transaction->update([
                'tx_hash'            => $txHash,
                'status'             => TransactionStatus::SUBMITTED,
                'submitted_at'       => now(),
                'signing_method'     => 'client',
                'broadcast_attempts' => $transaction->broadcast_attempts + 1,
            ]);

            return $transaction->fresh();
        } catch (\Exception $e) {
            $transaction->update([
                'error_message'      => $e->getMessage(),
                'broadcast_attempts' => $transaction->broadcast_attempts + 1,
            ]);

            throw $e;
        }
    }

    public function broadcastBackendSigned(Transaction $transaction): Transaction
    {
        if ($transaction->status !== TransactionStatus::PENDING) {
            throw new TransactionBroadcastFailedException('Transaction must be in PENDING status to broadcast');
        }

        try {
            $chain = $transaction->chain_type;
            $rawTx = $this->signTransaction($transaction);

            $txHash = $this->broadcastSignedTransaction($rawTx, $chain);

            $transaction->update([
                'tx_hash'            => $txHash,
                'status'             => TransactionStatus::SUBMITTED,
                'submitted_at'       => now(),
                'signing_method'     => 'backend',
                'broadcast_attempts' => $transaction->broadcast_attempts + 1,
            ]);

            return $transaction->fresh();
        } catch (\Exception $e) {
            $transaction->update([
                'error_message'      => $e->getMessage(),
                'broadcast_attempts' => $transaction->broadcast_attempts + 1,
            ]);

            throw $e;
        }
    }

    public function handleNonceConflict(Transaction $transaction): Transaction
    {
        try {
            $chain        = $transaction->chain_type;
            $currentNonce = $this->getNextNonce($transaction->from_address, $chain);

            Log::info('Nonce conflict detected, retrying with correct nonce', [
                'transaction_id' => $transaction->id,
                'current_nonce'  => $currentNonce,
            ]);

            $rawTx  = $this->signTransaction($transaction, $currentNonce);
            $txHash = $this->broadcastSignedTransaction($rawTx, $chain);

            $transaction->update([
                'tx_hash'      => $txHash,
                'status'       => TransactionStatus::SUBMITTED,
                'submitted_at' => now(),
                'retry_count'  => $transaction->retry_count + 1,
            ]);

            return $transaction->fresh();
        } catch (\Exception $e) {
            Log::error('Failed to handle nonce conflict', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
            throw new NonceConflictException('Failed to handle nonce conflict: ' . $e->getMessage());
        }
    }

    private function signTransaction(Transaction $transaction, ?int $nonce = null): string
    {
        $chain = $transaction->chain_type;

        if (!$chain->isEvm()) {
            throw new TransactionBroadcastFailedException(
                'Backend signing is only supported for EVM chains. Use client-side signing for ' . $chain->value . '.'
            );
        }

        $serviceKey = config('crypto.ico.buyer_key') ?: config('crypto.staking.signer_key');

        if (!$serviceKey) {
            throw new TransactionBroadcastFailedException(
                'No service wallet key configured (ICO_BUYER_KEY or STAKING_SIGNER_KEY). Use client-side signing for this transaction.'
            );
        }

        $txNonce = $nonce ?? $this->getNextNonce($transaction->from_address, $chain);

        $chainId = (int) match ($chain) {
            ChainType::ETH     => config('crypto.chains.eth.chain_id', 1),
            ChainType::BNB     => config('crypto.chains.bnb.chain_id', 56),
            ChainType::POLYGON => config('crypto.chains.polygon.chain_id', 137),
        };

        if ($transaction->gas_price_gwei && bccomp((string) $transaction->gas_price_gwei, '0', 8) > 0) {
            $gasPrice = bcmul((string) $transaction->gas_price_gwei, '1000000000', 0);
        } else {
            try {
                $gasPriceData = $this->nodeService->getGasPrice($chain);
                $gasPrice = $gasPriceData['gasPrice'] ?? (string) (20 * 1_000_000_000);
            } catch (\Throwable) {
                $gasPrice = (string) (20 * 1_000_000_000);
            }
        }

        $gasLimit = $transaction->gas_limit && bccomp((string) $transaction->gas_limit, '0', 8) > 0
            ? bcmul((string) $transaction->gas_limit, '1', 0)
            : ($transaction->contract_address ? '300000' : '21000');

        $rawAmount = (string) $transaction->amount;
        $weiValue  = str_contains($rawAmount, '.') ? bcmul($rawAmount, '1', 0) : $rawAmount;
        $weiValue  = ($weiValue === '' || $weiValue === '0') ? '0' : $weiValue;

        $data = $transaction->method_signature ? ltrim($transaction->method_signature, '0x') : '';

        $privateKey = ltrim($serviceKey, '0x');

        $toBytes  = $transaction->to_address ? hex2bin(ltrim($transaction->to_address, '0x')) : '';
        $dataBytes = $data ? hex2bin($data) : '';

        $rlpPreSign = [
            RlpEncoder::intToBytes($txNonce),
            RlpEncoder::intToBytes($gasPrice),
            RlpEncoder::intToBytes($gasLimit),
            $toBytes,
            RlpEncoder::intToBytes($weiValue),
            $dataBytes,
            RlpEncoder::intToBytes($chainId),
            '',
            '',
        ];

        $rlpEncoded = RlpEncoder::encode($rlpPreSign);
        $hash = Keccak::hash($rlpEncoded, 256);

        $ec = new EC('secp256k1');
        $keyPair = $ec->keyFromPrivate($privateKey, 'hex');
        $sig = $keyPair->sign($hash, ['canonical' => true]);

        $r = str_pad($sig->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($sig->s->toString(16), 64, '0', STR_PAD_LEFT);
        $v = $sig->recoveryParam + 2 * $chainId + 35;

        $rlpSigned = [
            RlpEncoder::intToBytes($txNonce),
            RlpEncoder::intToBytes($gasPrice),
            RlpEncoder::intToBytes($gasLimit),
            $toBytes,
            RlpEncoder::intToBytes($weiValue),
            $dataBytes,
            RlpEncoder::intToBytes($v),
            hex2bin($r),
            hex2bin($s),
        ];

        return '0x' . bin2hex(RlpEncoder::encode($rlpSigned));
    }

    public function getNextNonce(string $address, ChainType $chain): int
    {
        try {
            return $this->nodeService->getNonce($chain, $address);
        } catch (\Exception $e) {
            Log::error('Failed to get next nonce', [
                'address' => $address,
                'chain'   => $chain->value,
                'error'   => $e->getMessage(),
            ]);
            throw new TransactionBroadcastFailedException('Failed to get nonce: ' . $e->getMessage());
        }
    }
}
