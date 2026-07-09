# Infrastructure Overview

This section covers the cross-cutting backend infrastructure that operators and contributors
encounter outside of any single domain: health checks, dynamic scheduling, SSRF protection,
and the SyncLog system design.

---

## Health Checks

### HealthChecker

`App\Services\System\Health\HealthChecker` (`#[Singleton]`) is the main entry point for health
verification. It exposes two check modes:

**Quick check** (`quickCheck()`) — verifies basic PDO database connectivity only. Designed to
run in under a second. Does **not** mark the system healthy on success; only the deep check
does that.

**Deep check** (`deepCheck()`) — verifies all four critical components:

| Check name constant | What it tests |
|---|---|
| `CHECK_NAME_DB` | Full DB connection + `SELECT 1` |
| `CHECK_NAME_CACHE` | Cache write / read / delete round-trip |
| `CHECK_NAME_REDIS` | Redis `PING` |
| `CHECK_NAME_STORAGE` | Write + delete a temp file in `storage/framework/cache/` |

After running the component checks, `deepCheck()` dispatches a `HealthCheckEvent` so that
listeners can inject additional checks. It then calls `HealthTimer::markAsHealthy()` or
`markAsFailed()` depending on the aggregate result.

The unified `check()` method delegates to `quickCheck()` or `deepCheck()` based on the current
timer state — callers rarely need to call the specific check methods directly.

### HealthTimer

`App\Services\System\Health\HealthTimer` uses file-based state storage (under `storage/`)
rather than the database or cache. This avoids circular dependencies: the timer must work even
when those systems are down.

The escalation rule is built in: after 10 consecutive quick checks, `HealthTimer` automatically
escalates to a deep check on the next `check()` call. It also escalates immediately after any
failure.

### HTTP Endpoint

`GET /health` returns `200 OK` with body `"healthy"` when all checks pass, or
`503 Service Unavailable` with a JSON breakdown of per-component results when any check fails.
The endpoint is unauthenticated so monitoring tools can call it without credentials.

### Plugin Extension Point: HealthCheckEvent

`App\Services\System\Health\Events\HealthCheckEvent` is dispatched during every deep check.
Any listener can call `HealthCheckEvent::addResult(HealthCheckResult $result)` to inject a
custom check result into the aggregate. This is a **live extension point today** — no v3
required:

```php
// In your ServiceProvider, add a listener:
Event::listen(HealthCheckEvent::class, MyCustomHealthListener::class);

// In the listener:
public function handle(HealthCheckEvent $event): void
{
    $event->addResult(new HealthCheckResult(
        checkName: 'my_service',
        status: $this->myService->isUp() ? HealthCheckResult::STATUS_OK : HealthCheckResult::STATUS_ERROR,
        message: 'My service check',
    ));
}
```

Listeners auto-discovered from `app/Services/*/Listeners/` also work. See
[Plugin System Preview](100-Plugin-System-Preview.md) for a complete list of current extension
points.

---

## Dynamic Scheduling: ScheduleWithDynamicIntervalFactory

`App\Services\System\ScheduleWithDynamicIntervalFactory` enables scheduled commands whose
frequency is stored in database config or environment variables rather than being hardcoded in
`app/Console/Kernel.php`.

The `Schedule::commandWithDynamicInterval()` macro (registered in `AppServiceProvider`) calls
`makeJob()` under the hood. Interval strings map directly to method names on Laravel's
`ManagesFrequencies` trait — any valid frequency method works:

```php
// In AppServiceProvider or a command schedule registration:
Schedule::commandWithDynamicInterval(
    'ai:check-status',
    null,
    config('app.ai_check_status_interval', 'everyFiveMinutes')
);
```

The special sentinel value `"never"` disables a job entirely without removing it from the
schedule file. This lets operators disable resource-intensive jobs (e.g., `ai:check-status`)
without touching the codebase.

Interval args can be a JSON array string, a bare numeric value, or a plain string — the factory
parses them before forwarding to the frequency method.

---

## SSRF Protection: SsrfSafeGetterMacro

`App\Services\System\Http\SsrfSafeGetterMacro` registers the `Http::getSsrfSafe()` macro on
Laravel's HTTP client.

**Rule: all outbound HTTP requests from the backend must use `Http::getSsrfSafe()`** rather than
`Http::get()`.

The macro validates every URL — including every intermediate redirect hop — against a public-IP
allowlist. Requests that resolve to internal subnets (RFC 1918, loopback, link-local) are
blocked with a `SsrfBlockedException`. This protects against Server-Side Request Forgery
attacks where user-supplied URLs are used to reach internal services.

Cross-references: the encryption section and the utilities article mention this macro with a
single sentence each; the canonical description is here.

---

## SyncLog System (Designed, Currently Disabled)

The SyncLog system is designed to give the frontend a reliable stream of incremental change
notifications — so that a user's browser can stay in sync with room membership changes, new
messages, and keychain updates without polling.

The full design is implemented in code (`sync_logs` table, `SyncLogTracker`, per-entity
handlers for Room, Member, User, etc.) but is **currently disabled**. The `_hawki_sync_log`
meta slot is already injected into every mutating JSON:API response (the slot exists; the data
is currently empty).

Because the SyncLog system is a foundational piece of the v3 plugin architecture, its full
design documentation lives in [Plugin System Preview](100-Plugin-System-Preview.md) rather than
here.

---

## Related Articles

- [Plugin System Preview](100-Plugin-System-Preview.md) — all v3 extension points and the
  complete SyncLog design
- [Artisan Commands](200-Artisan-Commands.md) — full command reference
- [Announcements](300-Announcements.md) — system announcement management
