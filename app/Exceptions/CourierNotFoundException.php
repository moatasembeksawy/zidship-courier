<?php

namespace App\Exceptions;

class CourierNotFoundException extends \Exception
{
    public function render($request)
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'COURIER_NOT_FOUND',
                'message' => $this->getMessage(),
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ], 404);
    }
}
