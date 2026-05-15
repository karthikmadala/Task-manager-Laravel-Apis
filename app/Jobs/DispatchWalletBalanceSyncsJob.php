<?php

namespace App\Jobs;

use App\Models\Wallet;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Fan-out dispatcher: dispatches one UpdateWalletBalancesJob per active wallet.
 * Scheduled by the cron; each per-wallet job carries its own ShouldBeUnique lock.
 */
class DispatchWalletBalanceSyncsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public string $queue = 'low';
    public int $tries = 1;

    public int $uniqueFor = 300;

    public function handle(): void
    {
        $wallets = Wallet::active()->get();

        foreach ($wallets as $wallet) {
            dispatch(new UpdateWalletBalancesJob($wallet));
        }

        Log::info('DispatchWalletBalanceSyncsJob: dispatched sync jobs', [
            'wallet_count' => $wallets->count(),
        ]);
    }
}
