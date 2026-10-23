<?php
declare(strict_types=1);

namespace App\Services\Ai\LaravelAi;

/**
 * Marks the execution window in which the HTTP requests of an AI agent are made.
 *
 * {@see AbstractLaravelAgent} opens the scope around its {@see \Laravel\Ai\Concerns\Promptable::prompt()}
 * and {@see \Laravel\Ai\Concerns\Promptable::stream()} calls and carries the provider's stall timeout
 * (see {@see StallDetectionMiddleware}). Everything the underlying Laravel AI package requests while
 * the scope is open belongs to the agent request — including tool calls the agent makes mid-loop.
 * All other outgoing HTTP requests of the process stay outside the scope.
 *
 * The state is per-process and set for the duration of one call only, so it is safe under FPM and the
 * sequential queue worker, but it must not be used with long-running workers that serve concurrent
 * requests in one process.
 */
final class AgentRequestScope
{
    private static bool $active = false;

    private static int|null $stallSeconds = null;

    /**
     * Runs the given callback inside the agent request scope and returns its result.
     * The scope is always left, also when the callback throws.
     *
     * @template TReturn
     * @param callable(): TReturn $callback
     * @param int|null $stallSeconds Stall timeout of the agent's provider, null or 0 disables stall detection.
     * @return TReturn
     */
    public static function run(callable $callback, int|null $stallSeconds = null): mixed
    {
        self::$active = true;
        self::$stallSeconds = $stallSeconds;
        try {
            return $callback();
        } finally {
            self::$active = false;
            self::$stallSeconds = null;
        }
    }

    /**
     * True while an agent request runs.
     */
    public static function isActive(): bool
    {
        return self::$active;
    }

    /**
     * The stall timeout of the currently running agent request, null when there is none
     * or when stall detection is disabled for the provider.
     */
    public static function stallSeconds(): int|null
    {
        return self::$stallSeconds;
    }
}
