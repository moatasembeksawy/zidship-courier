<?php

namespace App\Contracts;

use App\DTOs\Courier\CancellationResponse;

/**
 * Interface for couriers that support shipment cancellation.
 * Not all couriers allow cancellation, so this is optional.
 */
interface SupportsCancellation
{
    /**
     * Cancel a shipment
     *
     * @param string $waybillNumber The waybill number to cancel
     * @return CancellationResponse Result of the cancellation attempt
     * @throws \App\Exceptions\CourierApiException
     */
    public function cancelShipment(string $waybillNumber): CancellationResponse;

    /**
     * Check if a shipment can be cancelled based on its current status
     */
    public function canBeCancelled(string $waybillNumber): bool;
}
