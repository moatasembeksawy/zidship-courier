<?php

namespace App\Jobs;

use App\Services\ShipmentService;
use App\Repositories\ShipmentRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateShipmentTrackingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public string $shipmentId
    ) {
        $this->onQueue('default');
    }

    public function handle(
        ShipmentService $shipmentService,
        ShipmentRepository $shipmentRepository
    ): void {
        try {
            $shipment = $shipmentRepository->find($this->shipmentId);

            if (in_array($shipment->status, ['delivered', 'cancelled', 'returned'])) {
                return;
            }
            $shipmentService->trackShipment($this->shipmentId, true);
        } catch (\Exception $e) {
        }
    }
}
