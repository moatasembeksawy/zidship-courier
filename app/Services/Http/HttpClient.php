<?php

namespace App\Services\Http;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Exceptions\CourierApiException;

/**
 * A wrapper around Laravel's HTTP client to provide unified retry logic,
 * error handling, and logging for all courier API communications.
 */
class HttpClient
{
    public function __construct(
        private int $maxRetries = 3,
        private int $retryDelay = 1000, // in milliseconds
        private int $timeout = 30 // in seconds
    ) {}

    /**
     * Make a POST request with retry logic.
     *
     * @throws CourierApiException
     */
    public function post(string $url, array $data, array $headers = []): array
    {
        return $this->send('post', $url, $data, $headers);
    }

    /**
     * Make a GET request with retry logic.
     *
     * @throws CourierApiException
     */
    public function get(string $url, array $query = [], array $headers = []): array
    {
        return $this->send('get', $url, $query, $headers);
    }

    /**
     * Make a DELETE request with retry logic.
     *
     * @throws CourierApiException
     */
    public function delete(string $url, array $data = [], array $headers = []): array
    {
        return $this->send('delete', $url, $data, $headers);
    }

    /**
     * Core method to send HTTP requests with robust error handling and retries.
     */
    private function send(string $method, string $url, array $payload, array $headers): array
    {
        try {
            $response = Http::withHeaders($headers)
                ->timeout($this->timeout)
                ->retry($this->maxRetries, $this->retryDelay, null, false)
                ->{$method}($url, $payload);

            if ($response->failed()) {
                $this->logError($response, $url);
                throw new CourierApiException(
                    "Courier API error: " . $response->reason(),
                    $response->status()
                );
            }

            Log::info("Courier API request successful", [
                'method' => strtoupper($method),
                'url' => $url,
                'status' => $response->status(),
            ]);

            return $response->json() ?? [];
        } catch (\Exception $e) {
            if ($e instanceof CourierApiException) {
                throw $e;
            }

            Log::error('Courier API request failed', [
                'url' => $url,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw new CourierApiException(
                "Courier API connection error: " . $e->getMessage(),
                503, // Service Unavailable
                $e
            );
        }
    }

    private function logError(Response $response, string $url): void
    {
        Log::error('Courier API request returned an error', [
            'url' => $url,
            'status' => $response->status(),
            'reason' => $response->reason(),
            'response_body' => $response->body(),
        ]);
    }
}
