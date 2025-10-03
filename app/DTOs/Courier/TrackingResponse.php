<?php

namespace App\DTOs\Courier;

use App\Enums\ShipmentStatus;

/**
 * Complete tracking information for a shipment
 */
final readonly class TrackingResponse
{
    /**
     * @param TrackingEvent[] $events
     */
    public function __construct(
        public string $waybillNumber,
        public ShipmentStatus $currentStatus,
        public array $events,
        public ?array $metadata = null,
    ) {}

    public function toArray(): array
    {
        return [
            'waybill_number' => $this->waybillNumber,
            'current_status' => $this->currentStatus->value,
            'current_status_label' => $this->currentStatus->label(),
            'events' => array_map(fn($event) => $event->toArray(), $this->events),
            'metadata' => $this->metadata,
        ];
    }

    public function getLatestEvent(): ?TrackingEvent
    {
        return !empty($this->events) ? $this->events[0] : null;
    }
}
