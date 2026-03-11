# Health Check

HAWKI includes a comprehensive health check system designed for Docker health checks and monitoring purposes.

## Endpoint

### Health Check
**Endpoint:** `GET /health`  
**Purpose:** Health check suitable for Docker health checks and external monitoring  
**Authentication:** None required

The endpoint uses a two-tier strategy internally (see [How It Works](#how-it-works)) to balance performance and thoroughness.

#### Success Response (200 OK)
```json
{
  "status": "healthy",
  "timestamp": "2026-03-10T10:30:45+00:00",
  "checks": [
    {
      "name": "database",
      "status": "ok",
      "message": "Database connection successful",
      "response_time": 5.23
    },
    {
      "name": "cache",
      "status": "ok",
      "message": "Cache system is operational",
      "response_time": 2.15
    },
    {
      "name": "redis",
      "status": "ok",
      "message": "Redis connection successful",
      "response_time": 1.87
    },
    {
      "name": "storage",
      "status": "ok",
      "message": "Storage is writable",
      "response_time": 3.42
    }
  ]
}
```

> **Note:** On quick checks (see below), only a single database-connectivity result with `"name": "quick_database"` is returned in `checks`.

#### Failure Response (503 Service Unavailable)
```json
{
  "status": "unhealthy",
  "timestamp": "2026-03-10T10:30:45+00:00",
  "checks": [
    {
      "name": "database",
      "status": "error",
      "message": "Database connection failed"
    },
    {
      "name": "cache",
      "status": "ok",
      "message": "Cache system is operational",
      "response_time": 2.15
    },
    {
      "name": "redis",
      "status": "error",
      "message": "Redis connection failed"
    },
    {
      "name": "storage",
      "status": "ok",
      "message": "Storage is writable",
      "response_time": 3.42
    }
  ]
}
```

## How It Works

The health check uses a **two-tier strategy** managed by `HealthTimer` to avoid overloading the system with expensive checks on every request:

### Quick Check
- Runs by default on most requests
- Only verifies basic **database connectivity** (fast)
- Does **not** update the healthy/failed state — only a successful deep check resets the state to healthy
- Result contains a single entry in `checks`

### Deep Check
A deep check is triggered automatically when:
1. The **previous check failed** (deep checks repeat until the system is healthy again), or
2. Every **10th quick check** (configurable)

A deep check verifies all four components (database, cache, Redis, storage). If all pass, the system is marked as healthy and quick checks resume. If any fail, the system is marked as failed and the next request will trigger another deep check.

### State Persistence
The timer state (failure flag and quick-test counter) is stored in a lightweight JSON file in the system temporary directory (`hawki_health_timer_marker.json`). This intentionally avoids relying on Laravel's cache or database, so the state remains available even when those services are degraded.

## Docker Integration

The health check is integrated into the Docker Compose configuration:

```yaml
healthcheck:
  test: curl --fail http://localhost/health || exit 1
  interval: 30s
  timeout: 10s
  retries: 3
  start_period: 30s
```

### Configuration Parameters

- **interval:** Health check runs every 30 seconds
- **timeout:** Each health check has 10 seconds to complete
- **retries:** Container is marked unhealthy after 3 consecutive failures
- **start_period:** Grace period of 30 seconds during container startup

## System Components Checked

### 1. Database
- Verifies database connectivity via PDO
- Executes a `SELECT 1` query to ensure the database is responsive
- Measures response time

### 2. Cache
- Tests cache read/write/delete operations
- Verifies data integrity (written value matches retrieved value)
- Measures response time
- Works with any configured cache driver (database, redis, file, etc.)

### 3. Redis
- Checks Redis connectivity
- Executes a `PING` command
- Measures response time

### 4. Storage
- Verifies that `storage/framework/cache` is writable
- Creates and deletes a temporary test file
- Measures response time

## Monitoring

You can monitor the health status of your containers using:

```bash
# Check health status
docker compose ps

# View health check logs
docker inspect --format='{{json .State.Health}}' hawki-app | jq

# Follow health check events
docker events --filter event=health_status
```

## Production Deployment

For production deployments, consider:

1. **External Monitoring:** Use the `/health` endpoint with external monitoring tools (Prometheus, Datadog, etc.)
2. **Alerting:** Set up alerts when health checks return `503`
3. **Load Balancers:** Configure load balancers to use `/health` for service availability checks
4. **Logging:** Monitor health check failures in application logs

## Troubleshooting

### Container Marked as Unhealthy

1. Check Docker logs: `docker compose logs app`
2. Access the health endpoint directly: `curl http://localhost/health`
3. Verify all services are running: `docker compose ps`
4. Check Laravel logs: `storage/logs/laravel.log`

### Common Issues

**Database Connection Failed**
- Verify MySQL container is running
- Check database credentials in `.env`
- Ensure database migrations have run

**Redis Connection Failed**
- Verify Redis container is running
- Check Redis configuration in `.env`
- Test Redis connectivity: `docker compose exec redis redis-cli ping`

**Storage Check Failed**
- Verify proper file permissions on `storage/framework/cache`
- Check available disk space
- Ensure storage directories exist

**Timer state out of sync**
- The timer state file is stored in the system temp directory as `hawki_health_timer_marker.json`
- Delete the file to reset the timer to a clean state: `rm $(php -r 'echo sys_get_temp_dir();')/hawki_health_timer_marker.json`

## Custom Health Checks

Custom checks are added by listening to the `HealthCheckEvent`, which is dispatched during every deep check. There is no need to modify the core `HealthChecker` class.

### How It Works

After the four built-in checks (database, cache, Redis, storage) finish, `HealthChecker::deepCheck()` dispatches a `HealthCheckEvent` carrying the initial `HealthCheckResultCollection`. Listeners receive the event and can append their own `HealthCheckResult` objects via `$event->addResult()`. The final collection — including all custom results — is then used to determine the overall health status.

If a custom result's `checkName` matches an existing entry, it **overwrites** that entry, which allows overriding built-in checks if needed.

### Creating a Listener

**1. Create the listener class**

```php
<?php
declare(strict_types=1);

namespace App\Listeners;

use App\Events\HealthCheckEvent;
use App\Services\System\Health\Value\HealthCheckResult;

class CustomServiceHealthListener
{
    public function handle(HealthCheckEvent $event): void
    {
        try {
            // Your custom check logic here
            // e.g. ping an external API, verify a queue worker is alive, etc.

            $event->addResult(new HealthCheckResult(
                checkName: 'custom_service',
                status: HealthCheckResult::STATUS_OK,
                message: 'Custom service is operational',
            ));
        } catch (\Throwable $e) {
            $event->addResult(new HealthCheckResult(
                checkName: 'custom_service',
                status: HealthCheckResult::STATUS_ERROR,
                message: 'Custom service check failed: ' . $e->getMessage(),
            ));
        }
    }
}
```

**2. Register the listener**

Add the mapping in `app/Providers/EventServiceProvider.php` (or wherever your project registers listeners):

```php
use App\Events\HealthCheckEvent;
use App\Listeners\CustomServiceHealthListener;

protected $listen = [
    HealthCheckEvent::class => [
        CustomServiceHealthListener::class,
    ],
];
```

The custom result will automatically appear in the `/health` response alongside the built-in checks:

```json
{
  "name": "custom_service",
  "status": "ok",
  "message": "Custom service is operational",
  "response_time": null
}
```

> **Tip:** Wrap your check logic in a `trackTime()` equivalent (a simple `microtime` call) and pass the result as the `responseTime` argument if you want response-time data to appear in the output.
