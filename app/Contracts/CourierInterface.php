<?php

namespace App\Contracts;

use App\DTOs\Courier\CreateWaybillRequest;
use App\DTOs\Courier\CreateWaybillResponse;
use App\DTOs\Courier\TrackingResponse;
use App\DTOs\Courier\WaybillLabel;

/**
 * Core courier interface that all courier implementations must implement.
 * Defines the minimum required functionality for any courier integration.
 */
interface CourierInterface
{
    /**
     * Get the unique code identifying this courier (e.g., 'aramex', 'smsa')
     */
    public function getCode(): string;

    /**
     * Get the display name of this courier
     */
    public function getName(): string;

    /**
     * Create a new waybill/shipment with the courier
     *
     * @throws \App\Exceptions\CourierApiException
     */
    public function createWaybill(CreateWaybillRequest $request): CreateWaybillResponse;

    /**
     * Retrieve the waybill label (usually PDF) for printing
     *
     * @param string $waybillNumber The waybill/tracking number
     * @return WaybillLabel Label data including content and format
     * @throws \App\Exceptions\CourierApiException
     */
    public function getWaybillLabel(string $waybillNumber): WaybillLabel;

    /**
     * Track a shipment by its waybill number
     *
     * @param string $waybillNumber The waybill/tracking number
     * @return TrackingResponse Current tracking information
     * @throws \App\Exceptions\CourierApiException
     */
    public function trackShipment(string $waybillNumber): TrackingResponse;

    /**
     * Map a courier-specific status to our unified status enum
     *
     * @param string $courierStatus The status code from the courier
     * @return \App\Enums\ShipmentStatus Our normalized status
     */
    public function mapStatus(string $courierStatus): \App\Enums\ShipmentStatus;

    public function isAvailable(): bool;
}
