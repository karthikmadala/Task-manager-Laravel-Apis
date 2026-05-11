<?php

namespace App\Jobs;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\TransactionMonitorService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class MonitorPendingTransactionsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    // Prevent duplicate queuing within this window.
    public int $uniqueFor = 300;

    public function handle(TransactionMonitorService $monitor): void
    {
        $pending = Transaction::whereIn('status', [
            TransactionStatus::PENDING->value,
            TransactionStatus::SUBMITTED->value,
        ])
            ->whereNotNull('tx_hash')
            ->get();

        if ($pending->isEmpty()) {
            return;
        }

        $updated = 0;
        $failed  = 0;

        foreach ($pending as $transaction) {
            try {
                $before = $transaction->status;
                $after  = $monitor->monitorTransaction($transaction);

                if ($after->status !== $before) {
                    $updated++;
                    Log::info('MonitorPendingTransactionsJob: status changed', [
                        'transaction_id' => $transaction->id,
                        'tx_hash'        => $transaction->tx_hash,
                        'from'           => $before->value,
                        'to'             => $after->status->value,
                    ]);
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('MonitorPendingTransactionsJob: failed to monitor transaction', [
                    'transaction_id' => $transaction->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        Log::info('MonitorPendingTransactionsJob complete', [
            'total'   => $pending->count(),
            'updated' => $updated,
            'failed'  => $failed,
        ]);
    }
}
