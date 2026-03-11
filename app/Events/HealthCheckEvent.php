<?php
declare(strict_types=1);


namespace App\Events;


use App\Services\System\Health\Value\HealthCheckResult;
use App\Services\System\Health\Value\HealthCheckResultCollection;

/**
 * Dispatched when a "deep" health check is performed,
 * allows custom listeners to react to the results of the health check and even add their own results to the collection before it is returned to the caller.
 */
class HealthCheckEvent
{
    public function __construct(
        private HealthCheckResultCollection $results
    )
    {
    }

    /**
     * Returns the collection of health check results associated with this event.
     * @return HealthCheckResultCollection
     */
    public function getResults(): HealthCheckResultCollection
    {
        return $this->results;
    }

    /**
     * Adds a new health check result to the existing collection of results.
     * This method creates a new collection with the added result to maintain immutability.
     * If there is already a result with the same check name, it will be overwritten with the new result.
     *
     * @param HealthCheckResult $result The health check result to be added.
     */
    public function addResult(HealthCheckResult $result): void
    {
        $newList = iterator_to_array($this->results, true);
        $newList[$result->checkName] = $result;
        $this->results = new HealthCheckResultCollection(...array_values($newList));
    }
}
