<?php

namespace App\Listeners;

use App\Events\ShipmentStatusChanged;
use Illuminate\Support\Facades\Log;

class NotifyCustomerOfStatusChange
{
    /**
     * Handle the event
     */
    public function handle(ShipmentStatusChanged $event): void
    {
        Log::info('Shipment status changed', [
            'shipment_id' => $event->shipment->id,
            'waybill_number' => $event->shipment->waybill_number,
            'old_status' => $event->oldStatus->value,
            'new_status' => $event->newStatus->value,
        ]);

        // Send notification to customer
        // Notification::route('mail', $event->shipment->receiver['email'])
        //     ->notify(new ShipmentStatusNotification($event->shipment));

        // Send SMS
        // SMS::to($event->shipment->receiver['phone'])
        //     ->send(new StatusUpdateMessage($event->shipment));

        // Push notification
        // FCM::sendTo($deviceToken, new StatusUpdate($event->shipment));
    }
}
