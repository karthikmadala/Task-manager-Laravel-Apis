<?php

namespace App\Jobs;

use App\Models\Wallet;
use App\Services\DepositDetectionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncIncomingTransactionsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public string $queue = 'low';
    public int $tries = 3;

    public int $timeout = 300;

    // Only one instance of this job may be queued at a time.
    public int $uniqueFor = 600;

    public function handle(DepositDetectionService $detector): void
    {
        $wallets = Wallet::active()->get();

        $scanned = 0;
        $failed  = 0;

        foreach ($wallets as $wallet) {
            try {
                $detector->scanWallet($wallet);
                $scanned++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('SyncIncomingTransactionsJob: wallet scan failed', [
                    'wallet_id' => $wallet->id,
                    'chain'     => $wallet->chain_type->value,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        Log::info('SyncIncomingTransactionsJob complete', [
            'scanned' => $scanned,
            'failed'  => $failed,
        ]);
    }
}
