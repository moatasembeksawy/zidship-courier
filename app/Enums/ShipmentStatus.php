<?php

namespace App\Enums;

/**
 * Unified shipment status across all couriers.
 * Every courier-specific status must map to one of these.
 */
enum ShipmentStatus: string
{
    case PENDING = 'pending';
    case PICKED_UP = 'picked_up';
    case IN_TRANSIT = 'in_transit';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case DELIVERY_FAILED = 'delivery_failed';
    case CANCELLED = 'cancelled';
    case RETURNED = 'returned';
    case EXCEPTION = 'exception';

    /**
     * Get human-readable label for the status
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Pickup',
            self::PICKED_UP => 'Picked Up',
            self::IN_TRANSIT => 'In Transit',
            self::OUT_FOR_DELIVERY => 'Out for Delivery',
            self::DELIVERED => 'Delivered',
            self::DELIVERY_FAILED => 'Delivery Failed',
            self::CANCELLED => 'Cancelled',
            self::RETURNED => 'Returned to Sender',
            self::EXCEPTION => 'Exception',
        };
    }

    /**
     * Check if this is a terminal status (no further updates expected)
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::DELIVERED,
            self::CANCELLED,
            self::RETURNED,
        ]);
    }

    /**
     * Check if this status indicates a problem
     */
    public function isProblematic(): bool
    {
        return in_array($this, [
            self::DELIVERY_FAILED,
            self::EXCEPTION,
        ]);
    }
}
