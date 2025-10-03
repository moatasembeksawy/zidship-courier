<?php

namespace App\Services;

use App\Contracts\CourierInterface;
use App\Contracts\SupportsCancellation;
use App\DTOs\Courier\CreateWaybillRequest;
use App\DTOs\Courier\TrackingResponse;
use App\DTOs\Courier\WaybillLabel;
use App\DTOs\Courier\CancellationResponse;
use App\Enums\ShipmentStatus;
use App\Events\ShipmentStatusChanged;
use App\Jobs\CreateWaybillJob;
use App\Jobs\UpdateShipmentTrackingJob;
use App\Models\Shipment;
use App\Repositories\ShipmentRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShipmentService
{
    public function __construct(
        private CourierFactory $courierFactory,
        private ShipmentRepository $shipmentRepository
    ) {}

    /**
     * Create a shipment, either synchronously or asynchronously.
     */
    public function createShipment(string $courierCode, CreateWaybillRequest $request, bool $async = false): Shipment|string
    {
        if ($async) {
            // For async, create a placeholder shipment and dispatch a job
            $shipment = $this->shipmentRepository->create([
                'id' => Str::uuid(),
                'courier_code' => $courierCode,
                'reference' => $request->reference,
                'status' => ShipmentStatus::PENDING->value,
                'shipper' => $request->shipper->toArray(),
                'receiver' => $request->receiver->toArray(),
                'package' => $request->package->toArray(),
            ]);

            CreateWaybillJob::dispatch($shipment->id, $request);

            return $shipment->id;
        }

        // For sync, perform the entire operation now
        $courier = $this->courierFactory->make($courierCode);
        $waybillResponse = $courier->createWaybill($request);

        return $this->shipmentRepository->create([
            'courier_code' => $courierCode,
            'waybill_number' => $waybillResponse->waybillNumber,
            'courier_reference' => $waybillResponse->courierReference,
            'reference' => $request->reference,
            'status' => ShipmentStatus::PENDING->value,
            'shipper' => $request->shipper->toArray(),
            'receiver' => $request->receiver->toArray(),
            'package' => $request->package->toArray(),
            'courier_metadata' => $waybillResponse->metadata,
        ]);
    }

    /**
     * Get shipment details by ID.
     */
    public function getShipment(string $id): Shipment
    {
        return $this->shipmentRepository->find($id) ?? abort(404, 'Shipment not found.');
    }

    /**
     * Get tracking information for a shipment.
     */
    public function trackShipment(string $id, bool $forceRefresh = false): TrackingResponse
    {
        $shipment = $this->getShipment($id);
        $cacheKey = "shipment:tracking:{$shipment->waybill_number}";

        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $courier = $this->courierFactory->make($shipment->courier_code);
        $trackingResponse = $courier->trackShipment($shipment->waybill_number);

        $this->updateShipmentFromTracking($shipment, $trackingResponse);

        $ttl = $trackingResponse->currentStatus->isTerminal()
            ? config('couriers.cache.tracking_ttl.terminal', 3600)
            : config('couriers.cache.tracking_ttl.active', 300);

        Cache::put($cacheKey, $trackingResponse, $ttl);

        return $trackingResponse;
    }

    /**
     * Get the waybill label for a shipment.
     */
    public function getLabel(string $id): WaybillLabel
    {
        $shipment = $this->getShipment($id);
        $courier = $this->courierFactory->make($shipment->courier_code);

        return $courier->getWaybillLabel($shipment->waybill_number);
    }

    /**
     * Cancel a shipment.
     */
    public function cancelShipment(string $id): CancellationResponse
    {
        $shipment = $this->getShipment($id);
        $courier = $this->courierFactory->make($shipment->courier_code);

        if (!$courier instanceof SupportsCancellation) {
            throw new \RuntimeException("Courier {$shipment->courier_code} does not support cancellation.");
        }

        if (!$courier->canBeCancelled($shipment->waybill_number)) {
            throw new \RuntimeException("Shipment is no longer in a state where it can be cancelled.");
        }

        $cancellationResponse = $courier->cancelShipment($shipment->waybill_number);

        if ($cancellationResponse->success) {
            $this->shipmentRepository->update($shipment->id, [
                'status' => ShipmentStatus::CANCELLED->value,
            ]);
        }

        return $cancellationResponse;
    }

    /**
     * Queue a tracking update for a shipment.
     */
    public function queueTrackingUpdate(string $shipmentId, int $delay = 0): void
    {
        UpdateShipmentTrackingJob::dispatch($shipmentId)->delay(now()->addSeconds($delay));
    }

    /**
     * Update our local shipment records based on new tracking data from a courier.
     */
    public function updateShipmentFromTracking(Shipment $shipment, TrackingResponse $tracking): void
    {
        DB::transaction(function () use ($shipment, $tracking) {
            $latestEvent = $tracking->getLatestEvent();
            if (!$latestEvent) return;

            // Update the main shipment status
            $this->shipmentRepository->update($shipment->id, [
                'status' => $tracking->currentStatus->value,
                'courier_raw_status' => $latestEvent->courierStatus,
            ]);

            // Create/update shipment event records
            foreach ($tracking->events as $event) {
                $shipment->events()->updateOrCreate(
                    [
                        'courier_status' => $event->courierStatus,
                        'occurred_at' => $event->timestamp,
                    ],
                    [
                        'status' => $event->status->value,
                        'description' => $event->description,
                        'location' => $event->location,
                        'metadata' => $event->metadata,
                    ]
                );
            }
        });
    }
}
