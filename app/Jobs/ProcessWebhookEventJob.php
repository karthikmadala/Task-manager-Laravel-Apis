<?php

namespace App\Jobs;

use App\Services\TransactionMonitorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessWebhookEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly array $event,
        public readonly string $source = 'unknown',
    ) {}

    public function handle(TransactionMonitorService $monitorService): void
    {
        try {
            Log::info('Processing webhook event', [
                'source' => $this->source,
                'event' => $this->event,
            ]);

            $monitorService->processWebhookEvent($this->event);

            Log::info('Webhook event processed successfully', [
                'source' => $this->source,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process webhook event', [
                'source' => $this->source,
                'event' => $this->event,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release(60);
            }
        }
    }
}
