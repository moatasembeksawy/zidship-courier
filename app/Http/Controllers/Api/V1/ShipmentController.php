<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Http\Resources\TrackingResource;
use App\Services\ShipmentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShipmentController extends Controller
{
    use ApiResponse;
    
    public function __construct(private ShipmentService $shipmentService) {}

    public function store(CreateShipmentRequest $request): JsonResponse
    {
        $createRequest = $request->toCreateWaybillRequest();
        $async = $request->boolean('async', false);

        $result = $this->shipmentService->createShipment(
            $request->input('courier_code'),
            $createRequest,
            $async
        );

        if ($async) {
            return $this->successResponse(
                ['id' => $result, 'status' => 'pending', 'message' => 'Shipment creation has been queued.'],
                'Request accepted.',
                Response::HTTP_ACCEPTED
            );
        }

        return $this->successResponse(
            new ShipmentResource($result),
            'Shipment created successfully.',
            Response::HTTP_CREATED
        );
    }

    public function show(string $id): JsonResponse
    {
        $shipment = $this->shipmentService->getShipment($id);
        return $this->successResponse(new ShipmentResource($shipment));
    }

    public function label(string $id): JsonResponse
    {
        $label = $this->shipmentService->getLabel($id);
        return $this->successResponse($label->toArray());
    }

    public function track(string $id, Request $request): JsonResponse
    {
        $forceRefresh = $request->boolean('force_refresh', false);
        $tracking = $this->shipmentService->trackShipment($id, $forceRefresh);
        return $this->successResponse(new TrackingResource($tracking));
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $result = $this->shipmentService->cancelShipment($id);
            if ($result->success) {
                return $this->successResponse($result->toArray(), 'Shipment cancelled successfully.');
            }
            return $this->errorResponse('CANCELLATION_FAILED', $result->message, Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return $this->errorResponse('CANCELLATION_NOT_SUPPORTED', $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
