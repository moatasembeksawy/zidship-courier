<?php

namespace App\Services;

use App\Jobs\ProcessWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WebhookService
{
    public function __construct(
        private CourierFactory $courierFactory,
        private ShipmentService $shipmentService
    ) {}

    /**
     * Verify webhook signature from courier
     */
    public function verifySignature(string $courier, Request $request): bool
    {
        $config = config("couriers.{$courier}");

        if (!isset($config['webhook_secret'])) {
            return true; // No verification if no secret configured
        }

        $signature = $request->header('X-Webhook-Signature');
        $timestamp = $request->header('X-Webhook-Timestamp', time());

        // Prevent replay attacks (5 minute window)
        if (abs(time() - $timestamp) > 300) {
            throw new \Exception('Webhook timestamp too old');
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $timestamp . $payload, $config['webhook_secret']);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new \Exception('Invalid webhook signature');
        }

        // Prevent replay with cache
        $replayKey = "webhook:replay:{$courier}:" . md5($signature . $timestamp);
        if (Cache::has($replayKey)) {
            throw new \Exception('Webhook replay detected');
        }

        Cache::put($replayKey, true, 600); // 10 minutes

        return true;
    }

    /**
     * Queue webhook for processing
     */
    public function processWebhook(string $courier, array $payload): void
    {
        ProcessWebhookJob::dispatch($courier, $payload);
    }

    /**
     * Handle webhook payload (called by job)
     */
    public function handleWebhook(string $courier, array $payload): void
    {
        $this->courierFactory->make($courier);

        // Extract waybill number from payload (courier-specific)
        $waybillNumber = $this->extractWaybillNumber($courier, $payload);

        if (!$waybillNumber) {
            throw new \Exception('Could not extract waybill number from webhook');
        }

        // Find shipment by waybill number
        $shipment = app(\App\Repositories\ShipmentRepository::class)
            ->findByWaybillNumber($waybillNumber);

        if (!$shipment) {
            throw new \Exception("Shipment not found for waybill: {$waybillNumber}");
        }

        // Trigger tracking update
        $this->shipmentService->queueTrackingUpdate($shipment->id);
    }

    private function extractWaybillNumber(string $courier, array $payload): ?string
    {
        // Courier-specific extraction logic
        return match ($courier) {
            'aramex' => $payload['ShipmentNumber'] ?? $payload['ID'] ?? $payload['tracking_number'] ?? null,
            //'smsa' => $payload['awb_number'] ?? $payload['tracking_number'] ?? null,
            default => $payload['ShipmentNumber'] ?? $payload['ID'] ?? $payload['tracking_number'] ?? null,
        };
    }
}
