# Health Checks

`App\Services\System\Health\HealthChecker` (`#[Singleton]`) is the main entry point for health verification. The `/health` endpoint is unauthenticated so monitoring tools can call it without credentials.

## Why two check modes

The `/health` endpoint is called frequently in a Docker setup — typically every 30 seconds by a load balancer or monitoring tool. Running a full health check (database, cache, Redis, storage) on every single call would bog down the system with trivial requests. To avoid this, the system has two check modes: a **quick check** that only verifies basic database connectivity, and a **deep check** that verifies all critical components. The `HealthTimer` decides which one to run.

## Quick check

`quickCheck()` verifies basic PDO database connectivity only. Designed to run in under a second. Does **not** mark the system healthy on success; only the deep check does that.

## Deep check

`deepCheck()` verifies four critical components:

- `CHECK_NAME_DB` — full DB connection + `SELECT 1`
- `CHECK_NAME_CACHE` — cache write / read / delete round-trip
- `CHECK_NAME_REDIS` — Redis `PING`
- `CHECK_NAME_STORAGE` — write + delete a temp file in `storage/framework/cache/`

After running the component checks, `deepCheck()` dispatches a `HealthCheckEvent` so listeners can inject additional checks (see [Extending HAWKI](../200-Concepts/220-Extending-HAWKI.md)). It then calls `HealthTimer::markAsHealthy()` or `markAsFailed()` depending on the aggregate result.

## `HealthTimer`

`App\Services\System\Health\HealthTimer` uses file-based state storage (under `storage/`) rather than the database or cache — the timer must work even when those systems are down.

The escalation rule: after 10 consecutive quick checks, `HealthTimer` automatically escalates to a deep check on the next `check()` call. In a Docker setup where `/health` is called every 30 seconds, this means roughly every 5 minutes a full deep check is executed. It also escalates immediately after any failure — the next call after a failed check is always a deep check, not a quick one.

The unified `check()` method delegates to `quickCheck()` or `deepCheck()` based on the current timer state — callers rarely need to call the specific check methods directly.

## HTTP endpoint

`GET /health` returns `200 OK` with body `"healthy"` when all checks pass, or `503 Service Unavailable` with a JSON breakdown of per-component results when any check fails.

## Extension point

The `HealthCheckEvent::addResult()` extension point is documented in [Extending HAWKI](../200-Concepts/220-Extending-HAWKI.md). Listeners auto-discovered from `app/Services/*/Listeners/` also work.
