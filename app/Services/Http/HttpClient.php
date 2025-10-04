<?php

namespace App\Services\Http;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Exceptions\CourierApiException;

class HttpClient
{
    public function __construct(
        private int $maxRetries = 3,
        private int $retryDelay = 1000, // in milliseconds
        private int $timeout = 30 // in seconds
    ) {}

    public function post(string $url, array $data, array $headers = []): array
    {
        return $this->send('post', $url, $data, $headers);
    }

    public function get(string $url, array $query = [], array $headers = []): array
    {
        return $this->send('get', $url, $query, $headers);
    }

    public function delete(string $url, array $data = [], array $headers = []): array
    {
        return $this->send('delete', $url, $data, $headers);
    }

    private function send(string $method, string $url, array $payload, array $headers): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                $response = Http::withHeaders($headers)
                    ->timeout($this->timeout)
                    ->{$method}($url, $payload);

                if ($response->successful()) {
                    return $response->json() ?? [];
                }

                if ($response->clientError()) { // 4xx errors - fail immediately
                    $this->logError($response, $url);
                    throw new CourierApiException("Courier API client error: " . $response->reason(), $response->status());
                }

                // Any other failure (5xx, etc.) is considered retryable
                $lastException = new CourierApiException("Courier API server error: " . $response->reason(), $response->status());
                $this->logError($response, $url);
            } catch (\Illuminate\Http\Client\ConnectionException | \Illuminate\Http\Client\RequestException $e) {
                // This single block now handles both connection and request exceptions (like timeouts)
                $lastException = $e;
                Log::warning("Courier API connection/request error", [
                    'url' => $url,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }

            // If we are here, an error occurred. Sleep if it's not the last attempt.
            if ($attempt < $this->maxRetries) {
                $delay = $this->calculateBackoff($attempt);
                usleep($delay * 1000); // usleep takes microseconds
            }
        }

        // If the loop finishes, all retries have failed.
        throw new CourierApiException(
            "Courier API request failed after {$this->maxRetries} attempts: " . $lastException->getMessage(),
            $lastException->getCode() ?: 503,
            $lastException
        );
    }

    private function calculateBackoff(int $attempt): int
    {
        return $this->retryDelay * pow(2, $attempt - 1);
    }



    private function logError(Response $response, string $url): void
    {
        Log::error('Courier API error response', [
            'url' => $url,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }
}
