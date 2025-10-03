<?php

namespace App\Services\Couriers;

use App\Contracts\CourierInterface;
use App\Services\Http\HttpClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

abstract class AbstractCourier implements CourierInterface
{
    protected HttpClient $httpClient;
    protected array $config;

    private string $circuitBreakerKey;
    private int $failureThreshold;
    private int $timeoutSeconds;
    private int $successThreshold;

    public function __construct(HttpClient $httpClient, array $config = [])
    {
        $this->httpClient = $httpClient;
        $this->config = $config;

        // Circuit Breaker Config
        $this->circuitBreakerKey = "courier:{$this->getCode()}:circuit_breaker";
        $cbConfig = config('couriers.circuit_breaker', []);
        $this->failureThreshold = $cbConfig['failure_threshold'] ?? 5;
        $this->timeoutSeconds = $cbConfig['timeout_seconds'] ?? 60;
        $this->successThreshold = $cbConfig['success_threshold'] ?? 2;
    }

    abstract public function getCode(): string;
    abstract public function getName(): string;
    abstract protected function getBaseUrl(): string;
    abstract protected function getAuthHeaders(): array;

    public function isAvailable(): bool
    {
        $state = Cache::get($this->circuitBreakerKey, [
            'status' => 'closed',
            'failures' => 0,
            'successes' => 0,
            'opened_at' => null
        ]);

        if ($state['status'] === 'open') {
            // If timeout has passed, move to half-open
            if (now()->timestamp > $state['opened_at'] + $this->timeoutSeconds) {
                $state['status'] = 'half_open';
                $state['successes'] = 0;
                Cache::put($this->circuitBreakerKey, $state, $this->timeoutSeconds * 2);
                return true; // Allow one test request
            }
            return false; // Circuit is open
        }

        return true; // Circuit is closed or half-open
    }

    /**
     * Records a successful API call, resetting the circuit breaker.
     */
    protected function recordSuccess(): void
    {
        $state = Cache::get($this->circuitBreakerKey);

        if (!$state) return; // Not tracking yet

        if ($state['status'] === 'half_open') {
            $state['successes']++;
            if ($state['successes'] >= $this->successThreshold) {
                // Close the circuit
                Cache::forget($this->circuitBreakerKey);
            } else {
                Cache::put($this->circuitBreakerKey, $state, $this->timeoutSeconds * 2);
            }
        } else {
            Cache::forget($this->circuitBreakerKey);
        }
    }

    /**
     * Records a failed API call, potentially opening the circuit.
     */
    protected function recordFailure(): void
    {
        $state = Cache::get($this->circuitBreakerKey, [
            'status' => 'closed',
            'failures' => 0
        ]);

        if ($state['status'] === 'open') return;

        $state['failures']++;

        if ($state['failures'] >= $this->failureThreshold) {
            $state['status'] = 'open';
            $state['opened_at'] = now()->timestamp;
        }

        Cache::put($this->circuitBreakerKey, $state, $this->timeoutSeconds * 2);
    }

    /**
     * A helper to wrap API calls with circuit breaker logic.
     */
    protected function makeApiCall(callable $callback)
    {
        if (!$this->isAvailable()) {
            throw new \App\Exceptions\CourierApiException("Courier service for {$this->getName()} is currently unavailable.", 503);
        }

        try {
            $result = $callback();
            $this->recordSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->recordFailure();
            throw $e; // Re-throw the original exception
        }
    }
}
