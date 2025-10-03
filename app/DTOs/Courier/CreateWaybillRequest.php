<?php

namespace App\DTOs\Courier;

/**
 * Request to create a new waybill
 */
final readonly class CreateWaybillRequest
{
    public function __construct(
        public Address $shipper,
        public Address $receiver,
        public Package $package,
        public string $reference,
        public string $serviceType = 'standard',
        public bool $cashOnDelivery = false,
        public ?float $codAmount = null,
        public ?string $notes = null,
        public ?array $metadata = null,
    ) {}

    public function toArray(): array
    {
        return [
            'shipper' => $this->shipper->toArray(),
            'receiver' => $this->receiver->toArray(),
            'package' => $this->package->toArray(),
            'reference' => $this->reference,
            'service_type' => $this->serviceType,
            'cash_on_delivery' => $this->cashOnDelivery,
            'cod_amount' => $this->codAmount,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
        ];
    }
}
