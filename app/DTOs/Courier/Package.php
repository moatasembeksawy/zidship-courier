<?php

namespace App\DTOs\Courier;

/**
 * Package/parcel details
 */
final readonly class Package
{
    public function __construct(
        public float $weight,          // in kg
        public float $length,          // in cm
        public float $width,           // in cm
        public float $height,          // in cm
        public string $weightUnit = 'kg',
        public string $dimensionUnit = 'cm',
        public ?string $description = null,
        public ?float $declaredValue = null,
        public ?string $currency = 'SAR',
    ) {}

    public function toArray(): array
    {
        return [
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'weight_unit' => $this->weightUnit,
            'dimension_unit' => $this->dimensionUnit,
            'description' => $this->description,
            'declared_value' => $this->declaredValue,
            'currency' => $this->currency,
        ];
    }
}
