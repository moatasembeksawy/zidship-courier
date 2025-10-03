<?php

namespace App\DTOs\Courier;

/**
 * Response from creating a waybill
 */
final readonly class CreateWaybillResponse
{
    public function __construct(
        public string $waybillNumber,
        public string $courierReference,
        public ?string $labelUrl = null,
        public ?array $metadata = null,
    ) {}

    public function toArray(): array
    {
        return [
            'waybill_number' => $this->waybillNumber,
            'courier_reference' => $this->courierReference,
            'label_url' => $this->labelUrl,
            'metadata' => $this->metadata,
        ];
    }
}
