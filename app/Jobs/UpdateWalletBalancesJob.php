<?php

namespace App\Jobs;

use App\Models\Wallet;
use App\Services\PortfolioService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class UpdateWalletBalancesJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum attempts before the job is marked failed.
     * PortfolioService::syncWallet() is exception-safe, so retries only occur
     * when the job itself throws (e.g. DB unavailable, DI resolution failure).
     */
    public string $queue = 'low';
    public int $tries = 3;

    /**
     * Exponential back-off in seconds between retries: 60s, 300s, 900s.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * Unique lock TTL — prevents duplicate syncs for the same wallet from
     * stacking up in the queue when a previous job is still processing.
     */
    public int $uniqueFor = 300;

    public function __construct(private readonly Wallet $wallet)
    {
        $this->onQueue('default');
    }

    public function handle(PortfolioService $portfolioService): void
    {
        Log::info('UpdateWalletBalancesJob: starting sync', [
            'wallet_id' => $this->wallet->id,
            'chain'     => $this->wallet->chain_type->value,
            'address'   => $this->wallet->address,
        ]);

        $portfolioService->syncWallet($this->wallet);

        Log::info('UpdateWalletBalancesJob: sync complete', [
            'wallet_id' => $this->wallet->id,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('UpdateWalletBalancesJob: job failed after all retries', [
            'wallet_id' => $this->wallet->id,
            'chain'     => $this->wallet->chain_type->value,
            'error'     => $e->getMessage(),
        ]);
    }

    /**
     * Unique job identifier — one sync job per wallet at a time.
     */
    public function uniqueId(): string
    {
        return $this->wallet->id;
    }
}
