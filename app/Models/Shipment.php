<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'courier_code',
        'waybill_number',
        'courier_reference',
        'reference',
        'status',
        'courier_raw_status',
        'shipper',
        'receiver',
        'package',
        'metadata',
        'courier_metadata',
        'error_message',
    ];

    protected $casts = [
        'shipper' => 'array',
        'receiver' => 'array',
        'package' => 'array',
        'metadata' => 'array',
        'courier_metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderByDesc('occurred_at');
    }

    public function getLatestEvent(): ?ShipmentEvent
    {
        return $this->events()->first();
    }
}
