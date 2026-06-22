<?php
declare(strict_types=1);

namespace App\Events\Traits;

/**
 * Replaces {@see \Illuminate\Foundation\Events\Dispatchable} for filter events.
 *
 * Filter events are synchronous, mutable hooks — they are never broadcast or queued.
 * This trait enforces that contract by omitting {@see \Illuminate\Foundation\Events\Dispatchable::broadcast()}
 * and changing the return type of all dispatch methods from mixed (listener responses) to
 * {@see static} (the event instance itself).
 *
 * Returning the event instance lets callers read back mutated state immediately after dispatch,
 * without having to hold a separate variable:
 *
 * ```php
 * $result = MyFilterEvent::dispatch($input, $tool)->getResult();
 * ```
 *
 * @see \Illuminate\Foundation\Events\Dispatchable for the standard (non-filter) variant
 */
trait DispatchableFilter
{
    /**
     * Dispatch the filter event and return the (potentially mutated) event instance.
     *
     * Listeners receive the same instance and may call setters on it.
     * The final state is readable on the returned object immediately after this call returns.
     */
    public static function dispatch(mixed ...$arguments): static
    {
        // @phpstan-ignore new.static
        $event = new static(...$arguments);
        event($event);
        return $event;
    }

    /**
     * Dispatch the filter event only when $condition is true.
     * Returns the event instance on dispatch, or null when the condition is false.
     */
    public static function dispatchIf(bool $condition, mixed ...$arguments): static|null
    {
        if ($condition) {
            return static::dispatch(...$arguments);
        }
        return null;
    }

    /**
     * Dispatch the filter event only when $condition is false.
     * Returns the event instance on dispatch, or null when the condition is true.
     */
    public static function dispatchUnless(bool $condition, mixed ...$arguments): static|null
    {
        if (!$condition) {
            return static::dispatch(...$arguments);
        }
        return null;
    }
}
