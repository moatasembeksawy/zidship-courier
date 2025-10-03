<?php

namespace App\Jobs;

use App\DTOs\Courier\CreateWaybillRequest;
use App\Services\ShipmentService;
use App\Repositories\ShipmentRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateWaybillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $shipmentId,
        public CreateWaybillRequest $request
    ) {
        $this->onQueue('high');
    }

    public function handle(
        ShipmentService $shipmentService,
        ShipmentRepository $shipmentRepository
    ): void {
        try {
            $shipment = $shipmentRepository->find($this->shipmentId);

            $shipmentService->createShipment(
                $shipment->courier_code,
                $this->request,
                false
            );

        } catch (\Exception $e) {

            if ($this->attempts() >= $this->tries) {
                $shipmentRepository->update($this->shipmentId, [
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }
}
