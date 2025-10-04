# ZidShip Courier Integration Framework

A Laravel framework for integrating multiple courier services (Aramex, SMSA, DHL, etc.) through one unified API.

### Core Features

*   **Unified API**: Create, track, and cancel shipments with any courier using the same API calls.
*   **Add New Couriers Easily**: A simple pattern lets you add new couriers without changing existing code.
*   **Asynchronous Support**: Option to queue shipment creation for a faster user experience.
*   **Built-in Resilience**: Automatic retries and a circuit breaker for handling API failures.
*   **Real-time Events**: React to shipment status changes in your application.
*    **Cache results** for better performance

---

## Architecture & Design Patterns

The framework is built on a clean, maintainable architecture designed for extensibility.

*   **Goal**: To allow new couriers to be added without modifying existing code.
*   **Core Idea**: The system is built arousnd a central `CourierInterface`, which defines a contract for what a courier must be able to do (create waybill, track, etc.).



1.  **Strategy Pattern**: This is the key to the whole system. Each courier (Aramex, SMSA, etc.) is a separate "strategy" class that implements the `CourierInterface`. Your application code doesn't care which courier it's using; it just calls the methods on the interface from the abstract class `AbstractCourier` that implement this interface, And if Support Cancellation implement `SupportsCancellation` Interface .

2.  **Factory Pattern**: A `CourierFactory` class is responsible for creating the correct courier "strategy" object based on a code (e.g., `'aramex'`). This decouples your business logic from the specific courier classes.

3.  **Adapter Pattern**: Each courier class also acts as an "adapter." It translates the framework's standard request format into the specific format required by the courier's external API.

This combination of patterns makes the system extremely flexible and easy to maintain.

---

## Key Design Decisions
- **DTOs**: Type safety prevents bugs
- **PostgreSQL**: JSONB for flexible courier, data Better full-text search
- **Redis**: Fast caching (Tracking data , Labels , Circuit breaker)
- **Queue**: Handle high volume
- **Circuit breaker**: Protect from failing APIs
- **Tests**: Confidence when changing code

---
### HTTP Retry Logic && Exponential backoff

**The Problem:** APIs fail sometimes (network issues, rate limits, server errors).

**My Solution:**
```php
class HttpClient {

    public function __construct(
        private int $maxRetries = 3,
        private int $retryDelay = 1000, // in milliseconds
        private int $timeout = 30 // in seconds
    ) {}
    
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
}
```

**Why this approach?**
- ✅ Exponential backoff (1s, 2s, 4s) gives API time to recover
- ✅ Don't retry 4xx errors (won't fix themselves)
- ✅ 3 retries = 90% success rate, 7 seconds max wait
- ✅ Circuit breaker prevents cascading failures
- ✅ Better logging for debugging


---

### Circuit Breaker

**The Problem:** If Aramex API is down, every request waits 7 seconds before failing.

**My Solution:**
```php
class AbstractCourier {

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

}
```

**How it works:**
```
Request 1-4: Fail → Count failures
Request 5: Fail → Block all requests for 60s
After 60s: Try again, reset if success
```

**Why?**
- ✅ Fast failures (no waiting)
- ✅ Protects our system
- ✅ Gives failed API time to recover
---
### Events: `ShipmentStatusChanged`

**The Problem:** When status changes, we need to:
- Send email to customer
- Send SMS
- Log it
- Update analytics
- Call merchant webhook


```php
event(new ShipmentStatusChanged($shipment, $oldStatus, $newStatus));
```
**Listeners:**
```php
class NotifyCustomerOfStatusChange {
    public function handle(ShipmentStatusChanged $event) {
        Log::info(...);
        Mail::send(...);
    }
}
```

**Auto-fire with Observer:**
```php
class ShipmentObserver {
    public function updated(Shipment $shipment) {
        event(new ShipmentStatusChanged(...));
    }
}
```

---

### **Async Mode for Shipments**

**Sync mode:**
```php
$shipment = $service->createShipment(..., async: false);
// Wait 2-5 seconds for courier API → Return shipment
```

**Async mode: using laravel queue**
```php
$id = $service->createShipment(..., async: true);
// Return immediately → Process in background
CreateWaybillJob::dispatch($shipment->id, $request);
```

**Decision:** Support both. Small shops need immediate response, large shops need throughput.

---

## Status Mapping

All couriers map to these 9 unified statuses:

| Unified Status | Meaning |
|----------------|---------|
| `pending` | Created, waiting for pickup |
| `picked_up` | Courier picked up package |
| `in_transit` | On the way to destination |
| `out_for_delivery` | Delivery agent has it |
| `delivered` | Successfully delivered ✅ |
| `delivery_failed` | Failed delivery attempt |
| `cancelled` | Shipment cancelled |
| `returned` | Returned to sender |
| `exception` | Problem occurred |

**Example:** Aramex returns "SH003", we map it to `in_transit`

---

## 🔧 Add a New Courier (3 Steps)

### Step 1: Create Courier Class

```php
// app/Services/Couriers/DhlCourier.php

class DhlCourier extends AbstractCourier implements SupportsCancellation 
{
    public function getCode(): string { return 'dhl'; }
    public function getName(): string { return 'DHL'; }
    
    public function createWaybill($request) {
        return $this->makeApiCall(function () use ($request) {
            // Call DHL API
            $response = $this->httpClient->post('https://dhl.com/api/shipments', ...);
            return new CreateWaybillResponse(...);
        });
    }
    
    public function mapStatus(string $courierStatus): ShipmentStatus {
        return match($courierStatus) {
            'AD' => ShipmentStatus::IN_TRANSIT,
            'OK' => ShipmentStatus::DELIVERED,
            default => ShipmentStatus::EXCEPTION,
        };
    }
    
    // Implement other methods...
}
```

### Step 2: Register in Factory

```php
// app/Services/CourierFactory.php

$this->couriers['dhl'] = function() {
    return new DhlCourier($this->httpClient, config('couriers.dhl'));
};
```

### Step 3: Add Configuration

```php
// config/couriers.php

'dhl' => [
    'base_url' => env('DHL_API_URL'),
    'api_key' => env('DHL_API_KEY'),
    'rate_limit' => ['requests' => 100, 'per_minutes' => 1],
],
```

**Done!** Your new courier works with all existing endpoints.

---

## Installation

```bash
# Clone and install
git clone <repo>
composer install

# Setup environment
cp .env.example .env
nano .env  # Add courier API keys

# Run migrations
php artisan migrate

# Start queue worker
php artisan queue:work

# Start server
php artisan serve
# Testing Unit And Feature
php artisan test
```

---

# Configure `.env`:
    Update your database, Redis, and courier credentials.
    ```env
    DB_CONNECTION=pgsql
    DB_HOST=127.0.0.1
    DB_PORT=5432
    DB_DATABASE=zidship
    DB_USERNAME=user
    DB_PASSWORD=password

    REDIS_HOST=127.0.0.1
    REDIS_PORT=6379

    QUEUE_CONNECTION=redis

    # Example for Aramex Courier
    ARAMEX_ENABLED=true
    ARAMEX_API_URL=https://ws.sbx.aramex.net/ShippingAPI.V2
    ARAMEX_USERNAME=your_aramex_username
    ARAMEX_PASSWORD=your_aramex_password
    ARAMEX_ACCOUNT_NUMBER=your_account_number
    ARAMEX_ACCOUNT_PIN=your_account_pin
    ARAMEX_ACCOUNT_ENTITY=your_account_entity # e.g., RUH
    ARAMEX_ACCOUNT_COUNTRY_CODE=SA
    ```
---

## API Documentation & Examples

### Endpoints Overview

| Method   | Endpoint                               | Description                               |
| :------- | :------------------------------------- | :---------------------------------------- |
| `POST`   | `/api/v1/shipments`                    | Create a new shipment                     |
| `GET`    | `/api/v1/shipments/{id}`               | Get shipment details                      |
| `GET`    | `/api/v1/shipments/{id}/track`         | Get current tracking information          |
| `GET`    | `/api/v1/shipments/{id}/label`         | Get waybill label                         |
| `DELETE` | `/api/v1/shipments/{id}`               | Cancel a shipment                         |
| `POST`   | `/api/v1/webhooks/{courier}`           | Endpoint for courier webhook notifications |

---

### Create Shipment
**Synchronous** : "async": false
**Asynchronous** : "async": true

**Request:** `POST /api/v1/shipments`

```bash
curl -X POST 'http://localhost:8000/api/v1/shipments' \
-H 'Content-Type: application/json' \
-H 'Accept: application/json' \
-d '{
    "async": true, 
    "courier_code": "aramex",
    "reference": "ORD-ZID-12345",
    "shipper": {
        "name": "ZidShip Warehouse",
        "phone": "+966500000001",
        "address_line_1": "123 Main Street",
        "city": "Riyadh",
        "state": "Riyadh",
        "postal_code": "11564",
        "country_code": "SA"
    },
    "receiver": {
        "name": "Customer Name",
        "phone": "+966500000002",
        "address_line_1": "456 Customer Ave",
        "city": "Jeddah",
        "state": "Makkah",
        "postal_code": "21589",
        "country_code": "SA"
    },
    "package": {
        "weight": 1.5,
        "length": 20,
        "width": 15,
        "height": 10
    }
}'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid-here",
    "waybill_number": "aramex123456789",
    "status": "created"
  }
}
```
