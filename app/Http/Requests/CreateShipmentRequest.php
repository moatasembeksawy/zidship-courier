<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\DTOs\Courier\CreateWaybillRequest;
use App\DTOs\Courier\Address;
use App\DTOs\Courier\Package;

class CreateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'courier_code' => 'required|string|max:50',
            'reference' => 'required|string|max:100',
            'service_type' => 'sometimes|string|in:standard,express,same_day',
            'cash_on_delivery' => 'sometimes|boolean',
            'cod_amount' => 'required_if:cash_on_delivery,true|numeric|min:0',
            'notes' => 'sometimes|string|max:500',
            'async' => 'sometimes|boolean',

            // Shipper
            'shipper' => 'required|array',
            'shipper.name' => 'required|string|max:100',
            'shipper.phone' => 'required|string|max:20',
            'shipper.email' => 'sometimes|email|max:100',
            'shipper.address_line_1' => 'required|string|max:200',
            'shipper.address_line_2' => 'sometimes|string|max:200',
            'shipper.city' => 'required|string|max:100',
            'shipper.state' => 'required|string|max:100',
            'shipper.postal_code' => 'required|string|max:20',
            'shipper.country_code' => 'required|string|size:2',

            // Receiver
            'receiver' => 'required|array',
            'receiver.name' => 'required|string|max:100',
            'receiver.phone' => 'required|string|max:20',
            'receiver.email' => 'sometimes|email|max:100',
            'receiver.address_line_1' => 'required|string|max:200',
            'receiver.address_line_2' => 'sometimes|string|max:200',
            'receiver.city' => 'required|string|max:100',
            'receiver.state' => 'required|string|max:100',
            'receiver.postal_code' => 'required|string|max:20',
            'receiver.country_code' => 'required|string|size:2',

            // Package
            'package' => 'required|array',
            'package.weight' => 'required|numeric|min:0.1',
            'package.length' => 'required|numeric|min:1',
            'package.width' => 'required|numeric|min:1',
            'package.height' => 'required|numeric|min:1',
            'package.description' => 'sometimes|string|max:200',
            'package.declared_value' => 'sometimes|numeric|min:0',
        ];
    }

    public function toCreateWaybillRequest(): CreateWaybillRequest
    {
        return new CreateWaybillRequest(
            shipper: new Address(
                name: $this->input('shipper.name'),
                phone: $this->input('shipper.phone'),
                addressLine1: $this->input('shipper.address_line_1'),
                addressLine2: $this->input('shipper.address_line_2'),
                city: $this->input('shipper.city'),
                state: $this->input('shipper.state'),
                postalCode: $this->input('shipper.postal_code'),
                countryCode: $this->input('shipper.country_code'),
                email: $this->input('shipper.email')
            ),
            receiver: new Address(
                name: $this->input('receiver.name'),
                phone: $this->input('receiver.phone'),
                addressLine1: $this->input('receiver.address_line_1'),
                addressLine2: $this->input('receiver.address_line_2'),
                city: $this->input('receiver.city'),
                state: $this->input('receiver.state'),
                postalCode: $this->input('receiver.postal_code'),
                countryCode: $this->input('receiver.country_code'),
                email: $this->input('receiver.email')
            ),
            package: new Package(
                weight: $this->input('package.weight'),
                length: $this->input('package.length'),
                width: $this->input('package.width'),
                height: $this->input('package.height'),
                description: $this->input('package.description'),
                declaredValue: $this->input('package.declared_value')
            ),
            reference: $this->input('reference'),
            serviceType: $this->input('service_type', 'standard'),
            cashOnDelivery: $this->boolean('cash_on_delivery'),
            codAmount: $this->input('cod_amount'),
            notes: $this->input('notes')
        );
    }
}
