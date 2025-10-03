<?php

namespace App\Providers;

use App\Events\ShipmentStatusChanged;
use App\Listeners\NotifyCustomerOfStatusChange;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        ShipmentStatusChanged::class => [
            NotifyCustomerOfStatusChange::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

}
