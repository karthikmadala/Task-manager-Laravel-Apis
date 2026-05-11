<?php

namespace App\Services;

use App\Enums\ChainType;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\Crypto\ExplorerService;
use Illuminate\Support\Facades\Log;

class TransactionMonitorService
{
    private const CONFIRMATION_THRESHOLD = 12;

    public function __construct(
        private readonly ExplorerService $explorer,
    ) {}

    public function monitorTransaction(Transaction $transaction): Transaction
    {
        if ($transaction->status->isTerminal()) {
            return $transaction;
        }

        $txHash = $transaction->tx_hash;

        if (! $txHash) {
            Log::warning('Cannot monitor transaction without hash', [
                'transaction_id' => $transaction->id,
            ]);

            return $transaction;
        }

        try {
            $chain  = $transaction->chain_type;
            $status = $this->checkTransactionStatus($txHash, $chain);

            return $this->updateTransactionStatus($transaction, $status);
        } catch (\Throwable $e) {
            Log::error('Failed to monitor transaction', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);

            return $transaction;
        }
    }

    /**
     * Uses Explorer gettxreceiptstatus — free, no RPC node required.
     * Returns SUBMITTED when the tx is not yet mined.
     */
    public function checkTransactionStatus(string $txHash, ChainType $chain): TransactionStatus
    {
        try {
            $receiptStatus = $this->explorer->getTxReceiptStatus($txHash, $chain);

            if ($receiptStatus === null) {
                return TransactionStatus::SUBMITTED; // not yet mined
            }

            return $receiptStatus === 'ok'
                ? TransactionStatus::CONFIRMED
                : TransactionStatus::FAILED;
        } catch (\Throwable $e) {
            Log::error('Failed to check transaction status', [
                'tx_hash' => $txHash,
                'chain'   => $chain->value,
                'error'   => $e->getMessage(),
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

                $receiptData = $this->getTransactionReceipt($transaction->tx_hash, $transaction->chain_type);

                if ($receiptData) {
                    $updateData['block_number']       = $receiptData['block_number'];
                    $updateData['gas_used']           = $receiptData['gas_used'];
                    $updateData['confirmations_count'] = $receiptData['confirmations'];
                }

                Log::info('Transaction confirmed', [
                    'transaction_id' => $transaction->id,
                    'tx_hash'        => $transaction->tx_hash,
                    'confirmations'  => $receiptData['confirmations'] ?? 0,
                ]);
            } elseif ($status === TransactionStatus::FAILED) {
                $updateData['error_message'] = 'Transaction failed on chain';

                Log::warning('Transaction failed on chain', [
                    'transaction_id' => $transaction->id,
                    'tx_hash'        => $transaction->tx_hash,
                ]);
            }

            $transaction->update($updateData);

            return $transaction->fresh();
        } catch (\Throwable $e) {
            Log::error('Failed to update transaction status', [
                'transaction_id' => $transaction->id,
                'status'         => $status->value,
                'error'          => $e->getMessage(),
            ]);

            return $transaction;
        }
    }

    public function updateConfirmations(Transaction $transaction): Transaction
    {
        if (! $transaction->tx_hash || $transaction->status !== TransactionStatus::CONFIRMED) {
            return $transaction;
        }

        try {
            $receiptData = $this->getTransactionReceipt($transaction->tx_hash, $transaction->chain_type);

            if ($receiptData) {
                $transaction->update(['confirmations_count' => $receiptData['confirmations']]);

                return $transaction->fresh();
            }

            return $transaction;
        } catch (\Throwable $e) {
            Log::error('Failed to update confirmations', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);

            return $transaction;
        }
    }

    public function isTransactionFinal(Transaction $transaction): bool
    {
        if ($transaction->status !== TransactionStatus::CONFIRMED) {
            return false;
        }

        return $transaction->confirmations_count >= self::CONFIRMATION_THRESHOLD;
    }

    /**
     * Derives block_number, gas_used, and confirmations via Explorer proxy calls.
     * No paid RPC node required.
     *
     * @return array{block_number: int, gas_used: string, confirmations: int}|null
     */
    private function getTransactionReceipt(string $txHash, ChainType $chain): ?array
    {
        try {
            // eth_getTransactionByHash is a free proxy call — returns block info for mined txs
            $tx = $this->explorer->callProxyPublic($chain, 'eth_getTransactionByHash', [$txHash]);

            if (! is_array($tx) || empty($tx['blockNumber'])) {
                return null; // not yet mined
            }

            $txBlockHex      = (string) $tx['blockNumber'];
            $txBlock         = (int) hexdec(ltrim($txBlockHex, '0x'));
            $currentBlockStr = $this->explorer->getCurrentBlockNumber($chain);
            $currentBlock    = $currentBlockInt = $currentBlockStr !== null ? (int) $currentBlockStr : $txBlock;

            // gas: use gasUsed from receipt if available, fallback to gas field
            $gasUsed = isset($tx['gas']) ? (string) hexdec(ltrim((string) $tx['gas'], '0x')) : '0';

            return [
                'block_number'  => $txBlock,
                'gas_used'      => $gasUsed,
                'confirmations' => max(0, $currentBlock - $txBlock),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to get transaction receipt via Explorer', [
                'tx_hash' => $txHash,
                'chain'   => $chain->value,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }
}
