<?php

namespace App\DTOs\Courier;

use App\Enums\ShipmentStatus;
use Carbon\Carbon;

/**
 * A single tracking event/checkpoint
 */
final readonly class TrackingEvent
{
    public function __construct(
        public ShipmentStatus $status,
        public string $courierStatus,
        public string $description,
        public Carbon $timestamp,
        public ?string $location = null,
        public ?array $metadata = null,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'courier_status' => $this->courierStatus,
            'description' => $this->description,
            'timestamp' => $this->timestamp->toIso8601String(),
            'location' => $this->location,
            'metadata' => $this->metadata,
        ];
    }
}
