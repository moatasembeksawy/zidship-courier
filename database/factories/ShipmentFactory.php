<?php

namespace Database\Factories;

use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'courier_code' => 'aramex',
            'waybill_number' => 'Aramex' . $this->faker->numerify('#########'),
            'courier_reference' => 'REF' . $this->faker->numerify('######'),
            'reference' => 'ORD-' . $this->faker->numerify('#####'),
            'status' => 'pending',
            'shipper' => [
                'name' => $this->faker->company,
                'phone' => $this->faker->phoneNumber,
                'address_line_1' => $this->faker->streetAddress,
                'city' => 'Riyadh',
                'state' => 'Riyadh',
                'postal_code' => '11564',
                'country_code' => 'SA',
            ],
            'receiver' => [
                'name' => $this->faker->name,
                'phone' => $this->faker->phoneNumber,
                'address_line_1' => $this->faker->streetAddress,
                'city' => 'Jeddah',
                'state' => 'Makkah',
                'postal_code' => '21589',
                'country_code' => 'SA',
            ],
            'package' => [
                'weight' => $this->faker->randomFloat(2, 0.5, 50),
                'length' => $this->faker->numberBetween(10, 100),
                'width' => $this->faker->numberBetween(10, 100),
                'height' => $this->faker->numberBetween(10, 100),
                'description' => $this->faker->sentence,
            ],
        ];
    }
}
