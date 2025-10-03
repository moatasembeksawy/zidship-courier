<?php

namespace App\Exceptions;

class CourierApiException extends \Exception
{
    public function __construct(
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function render($request)
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'COURIER_API_ERROR',
                'message' => $this->getMessage(),
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ], $this->code ?: 500);
    }
}
