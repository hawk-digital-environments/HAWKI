<?php
declare(strict_types=1);


namespace App\Services\System\Health\Value;


use Traversable;

readonly class HealthCheckResultCollection implements \JsonSerializable, \IteratorAggregate
{
    public array $results;

    public function __construct(
        HealthCheckResult ...$results
    )
    {
        $this->results = $results;
    }

    /**
     * Checks if all health check results are OK.
     *
     * @return bool True if all checks are OK, false if any check is an error.
     */
    public function isOk(): bool
    {
        foreach ($this->results as $result) {
            if ($result->isError()) {
                return false;
            }
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        $resultsArray = [];
        foreach ($this->results as $result) {
            $resultsArray[$result->checkName] = [
                'status' => $result->status,
                'message' => $result->message,
                'response_time' => $result->responseTime
            ];
        }

        return [
            'results' => $resultsArray,
            'status' => $this->isOk() ? HealthCheckResult::STATUS_OK : HealthCheckResult::STATUS_ERROR,
            'message' => $this->isOk() ? 'All checks passed.' : 'One or more checks failed.'
        ];
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        foreach ($this->results as $result) {
            yield $result->checkName => $result;
        }
    }
}
