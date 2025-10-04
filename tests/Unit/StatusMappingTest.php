<?php

namespace Tests\Unit;

use App\Enums\ShipmentStatus;
use App\Services\Couriers\AramexCourier;
use App\Services\Http\HttpClient;
use Tests\TestCase;

class StatusMappingTest extends TestCase
{
    private AramexCourier $courier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->courier = new AramexCourier(new HttpClient(), []);
    }

    /**  */
    public function test_maps_aramex_pending_status()
    {
        $status = $this->courier->mapStatus('SH001');
        $this->assertEquals(ShipmentStatus::PENDING, $status);
    }

    /**  */
    public function test_maps_aramex_picked_up_status()
    {
        $status = $this->courier->mapStatus('SH002');
        $this->assertEquals(ShipmentStatus::PICKED_UP, $status);
    }

    /**  */
    public function test_maps_aramex_in_transtest_status()
    {
        $status = $this->courier->mapStatus('SH003');
        $this->assertEquals(ShipmentStatus::IN_TRANSIT, $status);
    }

    /**  */
    public function test_maps_aramex_delivered_status()
    {
        $status = $this->courier->mapStatus('SH008');
        $this->assertEquals(ShipmentStatus::DELIVERED, $status);
    }

    /**  */
    public function test_maps_unknown_status_to_exception()
    {
        $status = $this->courier->mapStatus('UNKNOWN');
        $this->assertEquals(ShipmentStatus::EXCEPTION, $status);
    }

    /**  */
    public function test_status_can_identify_terminal_states()
    {
        $this->assertTrue(ShipmentStatus::DELIVERED->isTerminal());
        $this->assertTrue(ShipmentStatus::CANCELLED->isTerminal());
        $this->assertFalse(ShipmentStatus::IN_TRANSIT->isTerminal());
    }

    /**  */
    public function test_status_can_identify_problematic_states()
    {
        $this->assertTrue(ShipmentStatus::DELIVERY_FAILED->isProblematic());
        $this->assertTrue(ShipmentStatus::EXCEPTION->isProblematic());
        $this->assertFalse(ShipmentStatus::DELIVERED->isProblematic());
    }
}
