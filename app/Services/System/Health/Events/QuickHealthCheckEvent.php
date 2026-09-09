<?php

declare(strict_types=1);

namespace App\Services\System\Health\Events;

use App\Services\System\Health\Value\HealthCheckResult;
use App\Services\System\Health\Value\HealthCheckResultCollection;

/** Cheap local checks only. Kept separate from the stable deep-check event. */
class QuickHealthCheckEvent
{
    public function __construct(private HealthCheckResultCollection $results) {}

    public function getResults(): HealthCheckResultCollection
    {
        return $this->results;
    }

    public function addResult(HealthCheckResult $result): void
    {
        $results = iterator_to_array($this->results);
        $results[$result->checkName] = $result;
        $this->results = new HealthCheckResultCollection(...array_values($results));
    }
}
