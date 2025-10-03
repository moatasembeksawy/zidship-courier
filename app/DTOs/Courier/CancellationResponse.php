<?php

namespace App\DTOs\Courier;

/**
 * Response from a cancellation attempt
 */
final readonly class CancellationResponse
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?string $courierReference = null,
        public ?array $metadata = null,
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'courier_reference' => $this->courierReference,
            'metadata' => $this->metadata,
        ];
    }
}
