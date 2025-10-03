<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'waybill_number' => $this->waybill_number,
            'courier_code' => $this->courier_code,
            'courier_name' => $this->getCourierName(),
            'courier_reference' => $this->courier_reference,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'reference' => $this->reference,
            'shipper' => $this->shipper,
            'receiver' => $this->receiver,
            'package' => $this->package,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function getCourierName(): string
    {
        $names = [
            'aramex' => 'Aramex',
            'smsa' => 'SMSA Express',
            'shipbox' => 'Shipbox',
        ];

        return $names[$this->courier_code] ?? $this->courier_code;
    }

    private function getStatusLabel(): string
    {
        return \App\Enums\ShipmentStatus::from($this->status)->label();
    }
}
