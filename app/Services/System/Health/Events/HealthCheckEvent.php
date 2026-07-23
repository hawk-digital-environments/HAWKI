<?php
declare(strict_types=1);

namespace App\Services\System\Health\Events;

use App\Services\System\Health\Value\HealthCheckResult;
use App\Services\System\Health\Value\HealthCheckResultCollection;

/**
 * Dispatched during a "deep" health check performed by {@see \App\Services\System\Health\HealthChecker}.
 *
 * This event serves a dual purpose:
 * - **Notification**: listeners can react to the health check results and trigger alerts or logging.
 * - **Extension**: listeners can contribute additional health check results by calling
 *   {@see self::addResult()}, which allows plugging in custom component checks without
 *   modifying the core health checker.
 *
 * Basic and quick health checks do NOT dispatch this event — it only fires during deep checks.
 */
class HealthCheckEvent
{
    public function __construct(
        private HealthCheckResultCollection $results
    ) {}

    /**
     * Returns all health check results collected so far, including any contributed by listeners.
     */
    public function getResults(): HealthCheckResultCollection
    {
        return $this->results;
    }

    /**
     * Adds a custom health check result to the collection.
     *
     * Use this in a listener to report the health of a component that the core
     * health checker does not cover. If a result with the same check name already
     * exists it will be overwritten.
     */
    public function addResult(HealthCheckResult $result): void
    {
        $newList = iterator_to_array($this->results, true);
        $newList[$result->checkName] = $result;
        $this->results = new HealthCheckResultCollection(...array_values($newList));
    }
}
