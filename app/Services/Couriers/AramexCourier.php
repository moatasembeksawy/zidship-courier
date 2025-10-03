<?php

namespace App\Services\Couriers;

use Carbon\Carbon;
use App\Enums\ShipmentStatus;
use App\DTOs\Courier\WaybillLabel;
use App\DTOs\Courier\TrackingEvent;
use Illuminate\Support\Facades\Cache;
use App\DTOs\Courier\TrackingResponse;
use App\Contracts\SupportsCancellation;
use App\Exceptions\CourierApiException;
use App\DTOs\Courier\CancellationResponse;
use App\DTOs\Courier\CreateWaybillRequest;
use App\DTOs\Courier\CreateWaybillResponse;

class AramexCourier extends AbstractCourier implements SupportsCancellation
{
    public function getCode(): string
    {
        return 'aramex';
    }

    public function getName(): string
    {
        return 'Aramex';
    }

    protected function getBaseUrl(): string
    {
        return $this->config['base_url'] ?? 'https://ws.aramex.net/ShippingAPI.V2';
    }

    protected function getAuthHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Get Aramex client info for API requests
     */
    private function getClientInfo(): array
    {
        return [
            'UserName' => $this->config['username'],
            'Password' => $this->config['password'],
            'Version' => 'v1.0',
            'AccountNumber' => $this->config['account_number'],
            'AccountPin' => $this->config['account_pin'] ?? '000000',
            'AccountEntity' => $this->config['account_entity'] ?? 'RUH',
            'AccountCountryCode' => $this->config['account_country_code'] ?? 'SA',
        ];
    }

    public function createWaybill(CreateWaybillRequest $request): CreateWaybillResponse
    {
        try {
            $payload = $this->buildCreateShipmentPayload($request);
            $response = $this->httpClient->post(
                $this->getBaseUrl() . '/Shipping/Service_1_0.svc/json/CreateShipments',
                $payload,
                $this->getAuthHeaders()
            );

            if ($response['HasErrors'] ?? false) {
                $errors = $response['Notifications'] ?? [];
                $errorMessage = $this->formatErrors($errors);

                throw new CourierApiException("Aramex API Error: {$errorMessage}");
            }

            // Check if we have shipments in response
            if (empty($response['Shipments'])) {
                throw new CourierApiException('Aramex API returned no shipments in response');
            }

            $this->recordSuccess();

            $shipment = $response['Shipments'][0];

            // Validate we have required fields
            if (empty($shipment['ID'])) {
                throw new CourierApiException('Aramex API did not return shipment ID');
            }

            return new CreateWaybillResponse(
                waybillNumber: $shipment['ID'],
                courierReference: $shipment['ForeignHAWB'] ?? $request->reference,
                labelUrl: $shipment['ShipmentLabel']['LabelURL'] ?? null,
                metadata: [
                    'aramex_shipment_id' => $shipment['ID'],
                    'aramex_tracking_url' => $shipment['ShipmentDetails']['Origin'] ?? null,
                    'service_type' => $shipment['ServiceType'] ?? null,
                    'notifications' => $response['Notifications'] ?? [],
                ]
            );
        } catch (CourierApiException $e) {
            $this->recordFailure();
            throw $e;
        } catch (\Exception $e) {
            $this->recordFailure();

            throw new CourierApiException(
                "Failed to create Aramex waybill: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    public function getWaybillLabel(string $waybillNumber): WaybillLabel
    {
        try {
            $payload = [
                'ClientInfo' => $this->getClientInfo(),
                'ShipmentNumber' => $waybillNumber,
            ];

            $response = Cache::remember("label:Aramex" . $waybillNumber, 300, fn() => $this->httpClient->post(
                $this->getBaseUrl() . '/Shipping/Service_1_0.svc/json/PrintLabel',
                $payload,
                $this->getAuthHeaders()
            ));

            if ($response['HasErrors'] ?? false) {
                $errors = $response['Notifications'] ?? [];
                $errorMessage = $this->formatErrors($errors);
                throw new CourierApiException("Aramex API Error: {$errorMessage}");
            }

            $this->recordSuccess();

            $labelInfo = $response['ShipmentLabel'] ?? [];


            if (isset($labelInfo['LabelFileContents'])) {
                return new WaybillLabel(
                    content: $labelInfo['LabelFileContents'],
                    format: 'pdf',
                    contentType: 'application/pdf',
                    isUrl: false
                );
            }

            if (isset($labelInfo['LabelURL'])) {
                return new WaybillLabel(
                    content: $labelInfo['LabelURL'],
                    format: 'pdf',
                    contentType: 'application/pdf',
                    isUrl: true
                );
            }

            throw new CourierApiException('No label data found in Aramex response');
        } catch (\Exception $e) {
            Cache::forget("label:Aramex" . $waybillNumber);
            $this->recordFailure();
            throw new CourierApiException(
                "Failed to retrieve Aramex label: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    public function trackShipment(string $waybillNumber): TrackingResponse
    {
        try {
            $payload = [
                'ClientInfo' => $this->getClientInfo(),
                'Transaction' => [
                    'Reference1' => '',
                    'Reference2' => '',
                    'Reference3' => '',
                    'Reference4' => '',
                    'Reference5' => '',
                ],
                'Shipments' => [$waybillNumber],
                'GetLastTrackingUpdateOnly' => false,
            ];

            // Tracking data (5 min)
            $response = Cache::remember("track:Aramex" . $waybillNumber, 300, fn() =>
            $this->httpClient->post(
                $this->getBaseUrl() . '/Tracking/Service_1_0.svc/json/TrackShipments',
                $payload,
                $this->getAuthHeaders()
            ));



            if ($response['HasErrors'] ?? false) {
                $errors = $response['Notifications'] ?? [];
                $errorMessage = $this->formatErrors($errors);
                throw new CourierApiException("Aramex API Error: {$errorMessage}");
            }

            $this->recordSuccess();

            $trackingResults = $response['TrackingResults'] ?? [];

            if (empty($trackingResults)) {
                throw new CourierApiException('No tracking results found for waybill: ' . $waybillNumber, 200);
            }

            $result = $trackingResults[0];
            $updateEvents = $result['Value'] ?? [];

            $events = array_map(
                fn($event) => $this->mapTrackingEvent($event),
                $updateEvents
            );

            $currentStatus = !empty($events)
                ? $events[0]->status
                : ShipmentStatus::PENDING;

            return new TrackingResponse(
                waybillNumber: $waybillNumber,
                currentStatus: $currentStatus,
                events: $events,
                metadata: [
                    'origin' => $result['Value'][0]['Location'] ?? null,
                    'destination' => null,
                    'total_events' => count($events),
                ]
            );
        } catch (\Exception $e) {
            Cache::forget("track:Aramex" . $waybillNumber);
            $this->recordFailure();
            throw new CourierApiException(
                "Failed to track Aramex shipment: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    public function cancelShipment(string $waybillNumber): CancellationResponse
    {
        try {
            $payload = [
                'ClientInfo' => $this->getClientInfo(),
                'Transaction' => [
                    'Reference1' => '',
                    'Reference2' => '',
                    'Reference3' => '',
                ],
                'ShipmentNumber' => $waybillNumber,
                'Comments' => 'Cancelled by shipper',
            ];

            $response = $this->httpClient->post(
                $this->getBaseUrl() . '/Shipping/Service_1_0.svc/json/CancelShipment',
                $payload,
                $this->getAuthHeaders()
            );

            $hasErrors = $response['HasErrors'] ?? false;
            $notifications = $response['Notifications'] ?? [];

            $this->recordSuccess();

            if ($hasErrors) {
                return new CancellationResponse(
                    success: false,
                    message: $this->formatErrors($notifications),
                    courierReference: $waybillNumber,
                    metadata: ['notifications' => $notifications]
                );
            }

            return new CancellationResponse(
                success: true,
                message: 'Shipment cancelled successfully',
                courierReference: $waybillNumber,
                metadata: ['notifications' => $notifications]
            );
        } catch (\Exception $e) {
            $this->recordFailure();
            throw new CourierApiException(
                "Failed to cancel Aramex shipment: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    public function canBeCancelled(string $waybillNumber): bool
    {
        try {
            $tracking = $this->trackShipment($waybillNumber);

            return !in_array($tracking->currentStatus, [
                ShipmentStatus::DELIVERED,
                ShipmentStatus::OUT_FOR_DELIVERY,
                ShipmentStatus::CANCELLED,
                ShipmentStatus::RETURNED,
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function mapStatus(string $courierStatus): ShipmentStatus
    {
        return match (strtoupper(trim($courierStatus))) {
            // Initial statuses
            'SH001' => ShipmentStatus::PENDING,

            // Pickup statuses
            'SH011' => ShipmentStatus::PICKED_UP,

            // In transit statuses
            'SH013' => ShipmentStatus::IN_TRANSIT,

            // Out for delivery
            'SH003' => ShipmentStatus::OUT_FOR_DELIVERY,

            // Delivered
            'SH005' => ShipmentStatus::DELIVERED,

            // Failed delivery
            'SH009' => ShipmentStatus::DELIVERY_FAILED,
            'SH010' => ShipmentStatus::DELIVERY_FAILED,
            'SH375' => ShipmentStatus::DELIVERY_FAILED,

            // Exceptions
            'SH012' => ShipmentStatus::EXCEPTION,
            'SH013' => ShipmentStatus::EXCEPTION,
            'SH014' => ShipmentStatus::EXCEPTION,

            // Returns
            'SH009' => ShipmentStatus::RETURNED,

            // Cancelled
            'SH008' => ShipmentStatus::CANCELLED,

            default => ShipmentStatus::EXCEPTION,
        };
    }

    /**
     * Build the create shipment payload for Aramex API
     */
    private function buildCreateShipmentPayload(CreateWaybillRequest $request): array
    {
        return [
            'ClientInfo' => $this->getClientInfo(),
            'LabelInfo' => [
                'ReportID' => 9201,
                'ReportType' => 'URL',
            ],
            'Shipments' => [
                [
                    'Reference1' => $request->reference,
                    'Reference2' => '',
                    'Reference3' => '',
                    'Shipper' => $this->buildPartyAddress($request->shipper, 'shipper'),
                    'Consignee' => $this->buildPartyAddress($request->receiver, 'consignee'),
                    'ThirdParty' => $this->buildThirdParty(),
                    'ShippingDateTime' => '/Date(' . (time() * 1000) . ')/',
                    'DueDate' => '/Date(' . ((time() + 86400 * 3) * 1000) . ')/',
                    'Comments' => $request->notes ?? '',
                    'PickupLocation' => 'Reception',
                    'PickupGUID' => '',
                    'OperationsInstructions' => $request->notes ?? '',
                    'AccountingInstrcutions' => '',
                    'Details' => $this->buildShipmentDetails($request),
                    'Attachments' => [],
                    'ForeignHAWB' => $request->reference,
                    'TransportType' => 0,
                    'ShippingChargePaymentType' => 'P',
                    'CustomsValueAmount' => [
                        'Value' => $request->package->declaredValue ?? 0,
                        'CurrencyCode' => $request->package->currency ?? 'SAR',
                    ],
                    'CashOnDeliveryAmount' => [
                        'Value' => $request->cashOnDelivery ? $request->codAmount : 0,
                        'CurrencyCode' => 'SAR',
                    ],
                    'InsuranceAmount' => [
                        'Value' => 0,
                        'CurrencyCode' => 'SAR',
                    ],
                    'CashAdditionalAmount' => [
                        'Value' => 0,
                        'CurrencyCode' => 'SAR',
                    ],
                    'CashAdditionalAmountDescription' => '',
                    'CollectAmount' => [
                        'Value' => 0,
                        'CurrencyCode' => 'SAR',
                    ],
                    'Services' => $this->determineServices($request),
                    'Items' => [],
                    'DeliveryInstructions' => $request->notes ?? '',
                    'AdditionalProperties' => [],
                    'ContainsDangerousGoods' => false,
                ]
            ],
            'Transaction' => [
                'Reference1' => $request->reference,
                'Reference2' => '',
                'Reference3' => '',
                'Reference4' => '',
                'Reference5' => '',
            ],
        ];
    }

    /**
     * Build party address (shipper or consignee)
     */
    private function buildPartyAddress($address, string $type): array
    {
        return [
            'Reference1' => '',
            'Reference2' => '',
            'AccountNumber' => $type === 'shipper' ? $this->config['account_number'] : '',
            'PartyAddress' => [
                'Line1' => $address->addressLine1,
                'Line2' => $address->addressLine2 ?? '',
                'Line3' => '',
                'City' => $address->city,
                'StateOrProvinceCode' => $address->state,
                'PostCode' => $address->postalCode,
                'CountryCode' => $address->countryCode,
                'Longitude' => 0,
                'Latitude' => 0,
                'BuildingNumber' => '',
                'BuildingName' => '',
                'Floor' => '',
                'Apartment' => '',
                'POBox' => '',
                'Description' => '',
            ],
            'Contact' => [
                'Department' => '',
                'PersonName' => $address->name,
                'Title' => '',
                'CompanyName' => $address->name,
                'PhoneNumber1' => $address->phone,
                'PhoneNumber1Ext' => '',
                'PhoneNumber2' => '',
                'PhoneNumber2Ext' => '',
                'FaxNumber' => '',
                'CellPhone' => $address->phone,
                'EmailAddress' => $address->email ?? '',
                'Type' => '',
            ],
        ];
    }

    /**
     * Build third party info (empty for now)
     */
    private function buildThirdParty(): array
    {
        return [
            'Reference1' => '',
            'Reference2' => '',
            'AccountNumber' => '',
            'PartyAddress' => [
                'Line1' => '',
                'Line2' => '',
                'Line3' => '',
                'City' => '',
                'StateOrProvinceCode' => '',
                'PostCode' => '',
                'CountryCode' => '',
                'Longitude' => 0,
                'Latitude' => 0,
            ],
            'Contact' => [
                'Department' => '',
                'PersonName' => '',
                'Title' => '',
                'CompanyName' => '',
                'PhoneNumber1' => '',
                'PhoneNumber1Ext' => '',
                'PhoneNumber2' => '',
                'PhoneNumber2Ext' => '',
                'FaxNumber' => '',
                'CellPhone' => '',
                'EmailAddress' => '',
                'Type' => '',
            ],
        ];
    }

    /**
     * Build shipment details
     */
    private function buildShipmentDetails(CreateWaybillRequest $request): array
    {
        $weight = $request->package->weight;

        return [
            'Dimensions' => [
                'Length' => $request->package->length,
                'Width' => $request->package->width,
                'Height' => $request->package->height,
                'Unit' => 'CM',
            ],
            'ActualWeight' => [
                'Value' => $weight,
                'Unit' => 'KG',
            ],
            'ChargeableWeight' => [
                'Value' => $weight,
                'Unit' => 'KG',
            ],
            'DescriptionOfGoods' => $request->package->description ?? 'General Goods',
            'GoodsOriginCountry' => $request->shipper->countryCode,
            'NumberOfPieces' => 1,
            'ProductGroup' => 'EXP', // Express
            'ProductType' => $this->determineProductType($request->serviceType),
            'PaymentType' => 'P', // Prepaid
            'PaymentOptions' => '',
            'Services' => '',
            'CashOnDeliveryAmount' => [
                'Value' => $request->cashOnDelivery ? $request->codAmount : 0,
                'CurrencyCode' => 'SAR',
            ],
            'InsuranceAmount' => [
                'Value' => 0,
                'CurrencyCode' => 'SAR',
            ],
            'CollectAmount' => [
                'Value' => 0,
                'CurrencyCode' => 'SAR',
            ],
            'CustomsValueAmount' => [
                'Value' => $request->package->declaredValue ?? 0,
                'CurrencyCode' => 'SAR',
            ],
            'CashAdditionalAmount' => [
                'Value' => 0,
                'CurrencyCode' => 'SAR',
            ],
            'CashAdditionalAmountDescription' => '',
            'Items' => [],
        ];
    }

    /**
     * Determine Aramex product type based on service type
     */
    private function determineProductType(string $serviceType): string
    {
        return match (strtolower($serviceType)) {
            'express' => 'EPX', // Express
            'standard' => 'PDX', // Priority Document Express
            'same_day' => 'CDA', // Same Day
            default => 'PDX',
        };
    }

    private function determineServices(CreateWaybillRequest $request): string
    {
        $services = [];

        if ($request->cashOnDelivery) {
            $services[] = 'CODS';
        }

        return implode(',', $services);
    }

    /**
     * Map Aramex tracking event to our format
     */
    private function mapTrackingEvent(array $event): TrackingEvent
    {
        $courierStatus = $event['UpdateCode'] ?? $event['ProblemCode'] ?? 'UNKNOWN';
        $description = $event['UpdateDescription'] ?? $event['Comments'] ?? '';

        // Parse Aramex date format: /Date(1633024800000)/
        $timestamp = $this->parseAramexDate($event['UpdateDateTime'] ?? null);

        return new TrackingEvent(
            status: $this->mapStatus($courierStatus),
            courierStatus: $courierStatus,
            description: $description,
            timestamp: $timestamp,
            location: $event['UpdateLocation'] ?? null,
            metadata: [
                'problem_code' => $event['ProblemCode'] ?? null,
                'gross_weight' => $event['GrossWeight'] ?? null,
                'charged_weight' => $event['ChargedWeight'] ?? null,
            ]
        );
    }

    /**
     * Parse Aramex date format: /Date(1633024800000)/
     */
    private function parseAramexDate(?string $date): Carbon
    {
        if (!$date) {
            return Carbon::now();
        }

        // Extract timestamp from /Date(timestamp)/
        if (preg_match('/\/Date\((\d+)\)\//', $date, $matches)) {
            $timestamp = (int)($matches[1] / 1000); // Convert milliseconds to seconds
            return Carbon::createFromTimestamp($timestamp);
        }

        // Try to parse as regular date
        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            return Carbon::now();
        }
    }

    /**
     * Format Aramex API errors into readable message
     */
    private function formatErrors(array $notifications): string
    {
        if (empty($notifications)) {
            return 'Unknown error occurred';
        }

        $errors = [];
        foreach ($notifications as $notification) {
            $code = $notification['Code'] ?? 'UNKNOWN';
            $message = $notification['Message'] ?? 'No message provided';

            $errors[] = "[$code] $message";
        }

        return implode(' | ', $errors);
    }
}
