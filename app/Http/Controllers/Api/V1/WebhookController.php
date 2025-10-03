<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        private WebhookService $webhookService
    ) {}

    public function handle(string $courier, Request $request): JsonResponse
    {
        try {
            $this->webhookService->verifySignature($courier, $request);

            $this->webhookService->processWebhook($courier, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Webhook received and queued for processing'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
