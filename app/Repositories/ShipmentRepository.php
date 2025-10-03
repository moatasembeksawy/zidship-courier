<?php

namespace App\Repositories;

use App\Models\Shipment;
use Illuminate\Database\Eloquent\Collection;

class ShipmentRepository
{
    /**
     * Create a new shipment
     */
    public function create(array $data): Shipment
    {
        return Shipment::create($data);
    }

    /**
     * Find shipment by ID
     */
    public function find(string $id): ?Shipment
    {
        return Shipment::with('events')->find($id);
    }

    /**
     * Find shipment by waybill number
     */
    public function findByWaybillNumber(string $waybillNumber): ?Shipment
    {
        return Shipment::where('waybill_number', $waybillNumber)->first();
    }

    /**
     * Find shipment by reference
     */
    public function findByReference(string $reference): ?Shipment
    {
        return Shipment::where('reference', $reference)->first();
    }

    /**
     * Update shipment
     */
    public function update(string $id, array $data): bool
    {
        $shipment = $this->find($id);
        return $shipment ? $shipment->update($data) : false;
    }

    /**
     * Get all shipments by status
     */
    public function getByStatus(string $status): Collection
    {
        return Shipment::where('status', $status)->get();
    }

    /**
     * Get active shipments (not delivered/cancelled/returned)
     */
    public function getActiveShipments(): Collection
    {
        return Shipment::whereNotIn('status', [
            'delivered',
            'cancelled',
            'returned'
        ])->get();
    }

    /**
     * Get shipments by courier
     */
    public function getByCourier(string $courierCode): Collection
    {
        return Shipment::where('courier_code', $courierCode)->get();
    }
}
