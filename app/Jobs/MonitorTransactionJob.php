<?php

namespace App\Jobs;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\TransactionMonitorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class MonitorTransactionJob implements ShouldQueue
{
    use Queueable;

    public string $queue = 'critical';
    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly string $transactionId,
    ) {}

    public function handle(TransactionMonitorService $monitorService): void
    {
        $transaction = Transaction::find($this->transactionId);

        if (!$transaction) {
            Log::warning('Transaction not found for monitoring', [
                'transaction_id' => $this->transactionId,
            ]);
            return;
        }

        if ($transaction->status->isTerminal()) {
            Log::info('Transaction already in terminal state', [
                'transaction_id' => $this->transactionId,
                'status' => $transaction->status->value,
            ]);
            return;
        }

        try {
            $updatedTransaction = $monitorService->monitorTransaction($transaction);

            Log::info('Transaction monitored', [
                'transaction_id' => $this->transactionId,
                'status' => $updatedTransaction->status->value,
                'confirmations' => $updatedTransaction->confirmations_count,
            ]);

            // If still not terminal, schedule next check
            if (!$updatedTransaction->status->isTerminal()) {
                $this->release(config('transaction.monitoring.check_interval_minutes') * 60);
            }
        } catch (\Exception $e) {
            Log::error('Failed to monitor transaction', [
                'transaction_id' => $this->transactionId,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release(config('transaction.monitoring.retry_delay_seconds'));
            }
        }
    }
}
