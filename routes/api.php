<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\ShipmentController;


Route::prefix('v1')->group(function () {
    Route::prefix('shipments')->group(function () {
        Route::post('/', [ShipmentController::class, 'store']);
        Route::get('/{id}', [ShipmentController::class, 'show']);
        Route::get('/{id}/label', [ShipmentController::class, 'label']);
        Route::get('/{id}/track', [ShipmentController::class, 'track']);
        Route::delete('/{id}', [ShipmentController::class, 'destroy']);
    });
    Route::post('webhooks/{courier}', [WebhookController::class, 'handle']);
});
