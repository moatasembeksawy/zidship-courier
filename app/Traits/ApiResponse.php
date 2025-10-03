<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    public function successResponse(mixed $data, string $message = 'Success.', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => ['timestamp' => now()->toIso8601String()]
        ], $status);
    }

    public function errorResponse(string $code, string $message, int $status = 400, array $details = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message, 'details' => $details],
            'meta' => ['timestamp' => now()->toIso8601String()]
        ], $status);
    }
}