# Scheduling

HAWKI uses Laravel's built-in [task scheduler](https://laravel.com/docs/13.x/scheduling) for all scheduled commands. The only addition is `ScheduleWithDynamicIntervalFactory`, which allows a scheduled command's frequency to be read from the database or configuration at runtime rather than being hardcoded in the schedule definition.

## The dynamic interval macro

The `Schedule::commandWithDynamicInterval()` macro (registered in `AppServiceProvider`) wraps Laravel's `Schedule::command()` and resolves the frequency method dynamically. The interval string maps directly to a method name on Laravel's `ManagesFrequencies` trait — any valid frequency method works:

```php
Schedule::commandWithDynamicInterval(
    'ai:check-status',
    null,
    config('app.ai_check_status_interval', 'everyFiveMinutes')
);
```

Under the hood, `ScheduleWithDynamicIntervalFactory::makeJob()` calls `Schedule::command($command, $parameters)->$interval(...$intervalArgs)` — standard Laravel scheduling, just with the method name and arguments resolved at runtime.

## The `never` sentinel

The special interval value `"never"` disables a job entirely without removing it from the schedule file. `makeJob()` returns `null` and no schedule entry is created. This lets operators disable resource-intensive jobs (e.g., `ai:check-status`) without touching the codebase.

## Interval argument formats

Interval args can be a JSON array string, a bare numeric value, or a plain string — the factory parses them before forwarding to the frequency method. Invalid intervals or missing required arguments are logged and the job is skipped (returns `null`).

## Source

`app/Services/System/ScheduleWithDynamicIntervalFactory.php`
