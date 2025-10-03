<?php

namespace App\Observers;

use App\Models\Shipment;
use App\Events\ShipmentStatusChanged;
use App\Enums\ShipmentStatus;

class ShipmentObserver
{
    /**
     * Handle the Shipment "updated" event.
     */
    public function updated(Shipment $shipment): void
    {
        if ($shipment->isDirty('status')) {
            $oldStatus = ShipmentStatus::from($shipment->getOriginal('status'));
            $newStatus = ShipmentStatus::from($shipment->status);

            event(new ShipmentStatusChanged($shipment, $oldStatus, $newStatus));
        }
    }
}
