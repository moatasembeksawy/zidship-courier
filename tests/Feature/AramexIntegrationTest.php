<?php

namespace Tests\Feature;

use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AramexIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**  */
    public function test_can_create_aramex_shipment_via_api()
    {
        Http::fake([
            '*/CreateShipments' => Http::response([
                'HasErrors' => false,
                'Shipments' => [
                    [
                        'ID' => 'AMX123456789',
                        'ForeignHAWB' => 'TEST-001',
                        'ShipmentLabel' => [
                            'LabelURL' => 'https://aramex.com/label/123.pdf',
                        ],
                    ],
                ],
                'Notifications' => [],
            ], 200)
        ]);

        $response = $this->postJson('/api/v1/shipments', [
            'courier_code' => 'aramex',
            'reference' => 'TEST-001',
            'shipper' => [
                'name' => 'Test Shipper',
                'phone' => '+966501234567',
                'address_line_1' => '123 Test St',
                'city' => 'Riyadh',
                'state' => 'Riyadh',
                'postal_code' => '11564',
                'country_code' => 'SA',
            ],
            'receiver' => [
                'name' => 'Test Receiver',
                'phone' => '+966509876543',
                'address_line_1' => '456 Test Ave',
                'city' => 'Jeddah',
                'state' => 'Makkah',
                'postal_code' => '21589',
                'country_code' => 'SA',
            ],
            'package' => [
                'weight' => 2.5,
                'length' => 30,
                'width' => 20,
                'height' => 15,
                'description' => 'Test Package',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'waybill_number',
                    'courier_code',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('shipments', [
            'waybill_number' => 'AMX123456789',
            'courier_code' => 'aramex',
            'reference' => 'TEST-001',
        ]);
    }

    /**  */
    public function test_handles_aramex_api_errors()
    {
        Http::fake([
            '*/CreateShipments' => Http::response([
                'HasErrors' => true,
                'Notifications' => [
                    [
                        'Code' => 'ERR01',
                        'Message' => 'Invalid account credentials',
                    ],
                ],
            ], 200)
        ]);

        $response = $this->postJson('/api/v1/shipments', [
            'courier_code' => 'aramex',
            'reference' => 'TEST-002',
            'shipper' => [
                'name' => 'Test Shipper',
                'phone' => '+966501234567',
                'address_line_1' => '123 Test St',
                'city' => 'Riyadh',
                'state' => 'Riyadh',
                'postal_code' => '11564',
                'country_code' => 'SA',
            ],
            'receiver' => [
                'name' => 'Test Receiver',
                'phone' => '+966509876543',
                'address_line_1' => '456 Test Ave',
                'city' => 'Jeddah',
                'state' => 'Makkah',
                'postal_code' => '21589',
                'country_code' => 'SA',
            ],
            'package' => [
                'weight' => 2.5,
                'length' => 30,
                'width' => 20,
                'height' => 15,
            ],
        ]);

        $response->assertStatus(500);
    }


    public function test_validates_required_fields()
    {
        $response = $this->postJson('/api/v1/shipments', [
            'courier_code' => 'aramex',
            // Missing required fields
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reference', 'shipper', 'receiver', 'package']);
    }

}
