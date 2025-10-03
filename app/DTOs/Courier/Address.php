<?php

namespace App\DTOs\Courier;

/**
 * Standardized address data transfer object
 */
final readonly class Address
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $city,
        public string $state,
        public string $postalCode,
        public string $countryCode,
        public ?string $email = null,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postalCode,
            'country_code' => $this->countryCode,
            'email' => $this->email,
        ];
    }
}
