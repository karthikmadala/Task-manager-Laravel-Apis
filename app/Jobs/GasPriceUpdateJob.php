<?php

namespace App\Jobs;

use App\Enums\ChainType;
use App\Services\GasEstimationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GasPriceUpdateJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct()
    {
        //
    }

    public function handle(GasEstimationService $gasEstimationService): void
    {
        try {
            $chains = [ChainType::ETH, ChainType::BNB, ChainType::POLYGON];

            foreach ($chains as $chain) {
                if (!$chain->isEvm()) {
                    continue;
                }

                try {
                    Cache::forget("gas:oracle:{$chain->value}");

                    $oracle = $gasEstimationService->getGasOracle($chain);

                    Log::info('Gas oracle updated', [
                        'chain' => $chain->value,
                        'safe' => $oracle['safe'],
                        'propose' => $oracle['propose'],
                        'fast' => $oracle['fast'],
                        'base_fee' => $oracle['base_fee'],
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to update gas price for chain', [
                        'chain' => $chain->value,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to update gas prices', [
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release(30);
            }
        }
    }
}
