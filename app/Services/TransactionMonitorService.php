<?php

namespace App\Services;

use App\Enums\ChainType;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\Crypto\EvmRpcService;
use Illuminate\Support\Facades\Log;

class TransactionMonitorService
{
    public function __construct(
        private readonly EvmRpcService $evmRpcService,
    ) {}

    public function monitorTransaction(Transaction $transaction): Transaction
    {
        if ($transaction->status->isTerminal()) {
            return $transaction;
        }

        try {
            $chain = $transaction->chain_type; // already cast to ChainType enum by model
            $txHash = $transaction->tx_hash;

            if (!$txHash) {
                Log::warning('Cannot monitor transaction without hash', [
                    'transaction_id' => $transaction->id,
                ]);
                return $transaction;
            }

            $status = $this->checkTransactionStatus($txHash, $chain);

            return $this->updateTransactionStatus($transaction, $status);
        } catch (\Exception $e) {
            Log::error('Failed to monitor transaction', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            return $transaction;
        }
    }

    public function checkTransactionStatus(string $txHash, ChainType $chain): TransactionStatus
    {
        try {
            $rpcUrl = config("crypto.rpc.{$chain->value}");
            // call() returns the receipt object directly, throws if receipt is null (tx not yet mined)
            $receipt = $this->evmRpcService->call($rpcUrl, 'eth_getTransactionReceipt', [$txHash]);

            if (isset($receipt['status']) && hexdec($receipt['status']) === 0) {
                return TransactionStatus::FAILED;
            }

            return TransactionStatus::CONFIRMED;
        } catch (\Exception $e) {
            Log::error('Failed to check transaction status', [
                'tx_hash' => $txHash,
                'chain' => $chain->value,
                'error' => $e->getMessage(),
            ]);
            return TransactionStatus::SUBMITTED;
        }
    }

    public function updateTransactionStatus(Transaction $transaction, TransactionStatus $status): Transaction
    {
        if ($transaction->status === $status) {
            return $transaction;
        }

        try {
            $updateData = ['status' => $status];

            if ($status === TransactionStatus::CONFIRMED) {
                $updateData['confirmed_at'] = now();

                // Get additional receipt data
                $chain = $transaction->chain_type;
                $receiptData = $this->getTransactionReceipt($transaction->tx_hash, $chain);

                if ($receiptData) {
                    $updateData['block_number'] = $receiptData['block_number'];
                    $updateData['gas_used'] = $receiptData['gas_used'];
                    $updateData['confirmations_count'] = $receiptData['confirmations'];
                }

                Log::info('Transaction confirmed', [
                    'transaction_id' => $transaction->id,
                    'tx_hash' => $transaction->tx_hash,
                    'confirmations' => $receiptData['confirmations'] ?? 0,
                ]);
            } elseif ($status === TransactionStatus::FAILED) {
                $updateData['error_message'] = 'Transaction failed on chain';

                Log::warning('Transaction failed', [
                    'transaction_id' => $transaction->id,
                    'tx_hash' => $transaction->tx_hash,
                ]);
            }

            $transaction->update($updateData);
            return $transaction->fresh();
        } catch (\Exception $e) {
            Log::error('Failed to update transaction status', [
                'transaction_id' => $transaction->id,
                'status' => $status->value,
                'error' => $e->getMessage(),
            ]);
            return $transaction;
        }
    }

    public function updateConfirmations(Transaction $transaction): Transaction
    {
        if (!$transaction->tx_hash || $transaction->status !== TransactionStatus::CONFIRMED) {
            return $transaction;
        }

        try {
            $chain = $transaction->chain_type;
            $receiptData = $this->getTransactionReceipt($transaction->tx_hash, $chain);

            if ($receiptData) {
                $transaction->update([
                    'confirmations_count' => $receiptData['confirmations'],
                ]);

                return $transaction->fresh();
            }

            return $transaction;
        } catch (\Exception $e) {
            Log::error('Failed to update confirmations', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            return $transaction;
        }
    }

    public function isTransactionFinal(Transaction $transaction): bool
    {
        if ($transaction->status !== TransactionStatus::CONFIRMED) {
            return false;
        }

        $threshold = config('transaction.monitoring.confirmation_threshold', 12);
        return $transaction->confirmations_count >= $threshold;
    }

    public function processWebhookEvent(array $event): void
    {
        try {
            $txHash = $event['hash'] ?? null;
            $chainType = $event['chain'] ?? null;

            if (!$txHash || !$chainType) {
                Log::warning('Invalid webhook event', ['event' => $event]);
                return;
            }

            $chain = ChainType::tryFrom($chainType);
            if (!$chain) {
                Log::warning('Invalid chain type in webhook', ['chain' => $chainType]);
                return;
            }

            $transaction = Transaction::where('tx_hash', $txHash)
                ->where('chain_type', $chain->value)
                ->first();

            if (!$transaction) {
                Log::info('Webhook for unknown transaction', ['tx_hash' => $txHash]);
                return;
            }

            $status = $this->checkTransactionStatus($txHash, $chain);
            $this->updateTransactionStatus($transaction, $status);

            Log::info('Webhook processed successfully', [
                'transaction_id' => $transaction->id,
                'tx_hash' => $txHash,
                'status' => $status->value,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process webhook event', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getTransactionReceipt(string $txHash, ChainType $chain): ?array
    {
        try {
            $rpcUrl = config("crypto.rpc.{$chain->value}");

            // call() returns the receipt array directly; throws if null (not yet mined)
            $receipt = $this->evmRpcService->call($rpcUrl, 'eth_getTransactionReceipt', [$txHash]);
            $currentBlockHex = $this->evmRpcService->call($rpcUrl, 'eth_blockNumber', []);

            $currentBlock = hexdec((string) $currentBlockHex);
            $txBlock = hexdec($receipt['blockNumber']);

            return [
                'block_number' => $txBlock,
                'gas_used' => (string) hexdec($receipt['gasUsed']),
                'confirmations' => max(0, $currentBlock - $txBlock),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get transaction receipt', [
                'tx_hash' => $txHash,
                'chain' => $chain->value,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
