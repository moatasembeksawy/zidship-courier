<?php

namespace App\Services;

use App\Services\Http\HttpClient;
use App\Contracts\CourierInterface;
use Illuminate\Support\Facades\Log;
use App\Services\Couriers\AramexCourier;
use App\Exceptions\CourierNotFoundException;

class CourierFactory
{
    protected array $couriers = [];

    public function __construct(private HttpClient $httpClient)
    {
        $this->registerCouriers();
    }

    /**
     * Create a courier instance by its code.
     *
     * @throws CourierNotFoundException
     */
    public function make(string $courierCode): CourierInterface
    {
        if (!$this->hasCourier($courierCode)) {
            throw new CourierNotFoundException("Courier '{$courierCode}' is not found or is disabled.");
        }

        return call_user_func($this->couriers[$courierCode]);
    }

    /**
     * Check if a courier is registered and enabled.
     */
    public function hasCourier(string $courierCode): bool
    {
        return isset($this->couriers[$courierCode]) && config("couriers.{$courierCode}.enabled", false);
    }

    /**
     * Get a list of all available (registered and enabled) courier codes.
     */
    public function getAvailableCouriers(): array
    {
        return array_filter(
            array_keys($this->couriers),
            fn($code) => $this->hasCourier($code)
        );
    }

    /**
     * Register all available courier implementations.
     * New couriers must be added here.
     */
    private function registerCouriers(): void
    {
        $this->couriers['aramex'] = function () {
            return new AramexCourier(
                $this->httpClient,
                config('couriers.aramex', [])
            );
        };
    }
}
