<?php

use App\Jobs\DispatchWalletBalanceSyncsJob;
use App\Jobs\FetchTokenPricesJob;
use App\Jobs\MonitorPendingTransactionsJob;
use App\Jobs\SyncIncomingTransactionsJob;
use Illuminate\Support\Facades\Schedule;

// Deposit detection — scan all wallets for new incoming txs every 5 minutes.
Schedule::job(new SyncIncomingTransactionsJob)
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->name('sync-incoming-transactions');

// Confirmation tracking — poll submitted transactions every 2 minutes.
Schedule::job(new MonitorPendingTransactionsJob)
    ->everyTwoMinutes()
    ->withoutOverlapping(5)
    ->onOneServer()
    ->name('monitor-pending-transactions');

// Balance sync fan-out — dispatches one UpdateWalletBalancesJob per active wallet every 5 minutes.
Schedule::job(new DispatchWalletBalanceSyncsJob)
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->name('dispatch-wallet-balance-syncs');

// Price refresh — fetch native token prices from explorer every 5 minutes.
Schedule::job(new FetchTokenPricesJob)
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
    ->onOneServer()
    ->name('fetch-token-prices');
