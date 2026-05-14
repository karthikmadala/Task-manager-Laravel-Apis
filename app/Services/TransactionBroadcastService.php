<?php

namespace App\Services;

use App\Enums\ChainType;
use App\Enums\TransactionStatus;
use App\Exceptions\NonceConflictException;
use App\Exceptions\TransactionBroadcastFailedException;
use App\Models\Transaction;
use App\Services\Crypto\BlockchainNodeService;
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
        Log::warning('Backend signing not fully implemented', [
            'transaction_id' => $transaction->id,
        ]);

        throw new TransactionBroadcastFailedException('Backend signing requires integration with blockchain node service or private key management');
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
