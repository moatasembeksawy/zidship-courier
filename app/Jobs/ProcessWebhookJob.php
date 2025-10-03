<?php

namespace App\Jobs;

use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public string $courier,
        public array $payload
    ) {
        $this->onQueue('default');
    }

    public function handle(WebhookService $webhookService): void
    {
        try {
            $webhookService->handleWebhook($this->courier, $this->payload);
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
