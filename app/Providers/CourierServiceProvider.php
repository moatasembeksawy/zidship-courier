<?php

namespace App\Providers;

use App\Services\CourierFactory;
use App\Services\Http\HttpClient;
use App\Services\ShipmentService;
use App\Repositories\ShipmentRepository;
use Illuminate\Support\ServiceProvider;

class CourierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HttpClient::class, function ($app) {
            return new HttpClient(
                maxRetries: config('couriers.http.retry.times', 3),
                retryDelay: config('couriers.http.retry.sleep', 1000)
            );
        });

        $this->app->singleton(CourierFactory::class, function ($app) {
            return new CourierFactory($app->make(HttpClient::class));
        });

        $this->app->singleton(ShipmentService::class, function ($app) {
            return new ShipmentService(
                $app->make(CourierFactory::class),
                $app->make(ShipmentRepository::class)
            );
        });
        
        $this->app->singleton(ShipmentRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
