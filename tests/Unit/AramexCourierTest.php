<?php

namespace Tests\Unit;

use App\Services\Couriers\AramexCourier;
use App\Services\Http\HttpClient;
use App\Enums\ShipmentStatus;
use App\DTOs\Courier\CreateWaybillRequest;
use App\DTOs\Courier\Address;
use App\DTOs\Courier\Package;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AramexCourierTest extends TestCase
{
    private AramexCourier $courier;

    protected function setUp(): void
    {
        parent::setUp();

        $config = [
            'username' => 'test@example.com',
            'password' => 'test123',
            'account_number' => '12345',
            'account_pin' => '000000',
            'account_entity' => 'RUH',
            'account_country_code' => 'SA',
        ];

        $this->courier = new AramexCourier(new HttpClient(), $config);
    }

    /**  */
    public function test_has_correct_courier_info()
    {
        $this->assertEquals('aramex', $this->courier->getCode());
        $this->assertEquals('Aramex', $this->courier->getName());
    }

    /**  */
    public function test_maps_aramex_created_status()
    {
        $status = $this->courier->mapStatus('SH001');
        $this->assertEquals(ShipmentStatus::PENDING, $status);

        $status = $this->courier->mapStatus('CREATED');
        $this->assertEquals(ShipmentStatus::PENDING, $status);
    }

    /**  */
    public function test_maps_aramex_collected_status()
    {
        $status = $this->courier->mapStatus('SH002');
        $this->assertEquals(ShipmentStatus::PICKED_UP, $status);

        $status = $this->courier->mapStatus('COLLECTED');
        $this->assertEquals(ShipmentStatus::PICKED_UP, $status);
    }

    /**  */
    public function test_maps_aramex_in_transtest_status()
    {
        $status = $this->courier->mapStatus('SH003');
        $this->assertEquals(ShipmentStatus::IN_TRANSIT, $status);

        $status = $this->courier->mapStatus('IN_TRANSIT');
        $this->assertEquals(ShipmentStatus::IN_TRANSIT, $status);
    }

    /**  */
    public function test_maps_aramex_out_for_delivery_status()
    {
        $status = $this->courier->mapStatus('SH007');
        $this->assertEquals(ShipmentStatus::OUT_FOR_DELIVERY, $status);

        $status = $this->courier->mapStatus('OUT_FOR_DELIVERY');
        $this->assertEquals(ShipmentStatus::OUT_FOR_DELIVERY, $status);
    }

    /**  */
    public function test_maps_aramex_delivered_status()
    {
        $status = $this->courier->mapStatus('SH008');
        $this->assertEquals(ShipmentStatus::DELIVERED, $status);

        $status = $this->courier->mapStatus('DELIVERED');
        $this->assertEquals(ShipmentStatus::DELIVERED, $status);
    }

    /**  */
    public function test_maps_aramex_cancelled_status()
    {
        $status = $this->courier->mapStatus('SH017');
        $this->assertEquals(ShipmentStatus::CANCELLED, $status);

        $status = $this->courier->mapStatus('CANCELLED');
        $this->assertEquals(ShipmentStatus::CANCELLED, $status);
    }

    /**  */
    public function test_maps_aramex_returned_status()
    {
        $status = $this->courier->mapStatus('SH016');
        $this->assertEquals(ShipmentStatus::RETURNED, $status);

        $status = $this->courier->mapStatus('RETURNED');
        $this->assertEquals(ShipmentStatus::RETURNED, $status);
    }

    /**  */
    public function test_maps_unknown_status_to_exception()
    {
        $status = $this->courier->mapStatus('UNKNOWN_STATUS');
        $this->assertEquals(ShipmentStatus::EXCEPTION, $status);
    }

    /**  */
    public function test_can_create_waybill()
    {
        Http::fake([
            '*/CreateShipments' => Http::response([
                'HasErrors' => false,
                'Shipments' => [
                    [
                        'ID' => 'AMX123456789',
                        'ForeignHAWB' => 'ORD-12345',
                        'ShipmentLabel' => [
                            'LabelURL' => 'https://aramex.com/label/123.pdf',
                        ],
                    ],
                ],
                'Notifications' => [],
            ], 200)
        ]);

        $request = new CreateWaybillRequest(
            shipper: new Address(
                name: 'Test Shipper',
                phone: '+966501234567',
                addressLine1: '123 Test St',
                addressLine2: null,
                city: 'Riyadh',
                state: 'Riyadh',
                postalCode: '11564',
                countryCode: 'SA'
            ),
            receiver: new Address(
                name: 'Test Receiver',
                phone: '+966509876543',
                addressLine1: '456 Test Ave',
                addressLine2: null,
                city: 'Jeddah',
                state: 'Makkah',
                postalCode: '21589',
                countryCode: 'SA'
            ),
            package: new Package(
                weight: 2.5,
                length: 30,
                width: 20,
                height: 15
            ),
            reference: 'ORD-12345'
        );

        $response = $this->courier->createWaybill($request);

        $this->assertEquals('AMX123456789', $response->waybillNumber);
        $this->assertEquals('ORD-12345', $response->courierReference);
    }

    /**  */
    public function test_can_track_shipment()
    {
        Http::fake([
            '*/TrackShipments' => Http::response([
                'HasErrors' => false,
                'TrackingResults' => [
                    [
                        'Value' => [
                            [
                                'UpdateCode' => 'SH008',
                                'UpdateDescription' => 'Shipment delivered',
                                'UpdateDateTime' => '/Date(1633024800000)/',
                                'UpdateLocation' => 'Jeddah',
                            ],
                        ],
                    ],
                ],
            ], 200)
        ]);

        $tracking = $this->courier->trackShipment('AMX123456789');

        $this->assertEquals('AMX123456789', $tracking->waybillNumber);
        $this->assertEquals(ShipmentStatus::DELIVERED, $tracking->currentStatus);
        $this->assertCount(1, $tracking->events);
    }

    /**  */
    public function test_can_cancel_shipment()
    {
        Http::fake([
            '*/CancelShipment' => Http::response([
                'HasErrors' => false,
                'Notifications' => [
                    [
                        'Code' => 'SUCCESS',
                        'Message' => 'Shipment cancelled successfully',
                    ],
                ],
            ], 200)
        ]);

        $result = $this->courier->cancelShipment('AMX123456789');

        $this->assertTrue($result->success);
        $this->assertEquals('Shipment cancelled successfully', $result->message);
    }

    /**  */
    public function test_can_get_label()
    {
        Http::fake([
            '*/PrintLabel' => Http::response([
                'HasErrors' => false,
                'ShipmentLabel' => [
                    'LabelFileContents' => base64_encode('PDF_CONTENT_HERE'),
                ],
            ], 200)
        ]);

        $label = $this->courier->getWaybillLabel('AMX123456789');

        $this->assertEquals('pdf', $label->format);
        $this->assertFalse($label->isUrl);
    }
}
