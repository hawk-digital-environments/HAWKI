<?php
declare(strict_types=1);


namespace App\Services\System\Health\Value;


use Traversable;

/**
 * An immutable, iterable collection of {@see HealthCheckResult} objects.
 *
 * Returned by {@see HealthChecker::check()} and {@see HealthChecker::deepCheck()} after
 * running all configured component checks. Also passed to {@see \App\Services\System\Health\Events\HealthCheckEvent}
 * so listeners can append their own results.
 *
 * Iterating over the collection yields the results keyed by their check name:
 * ```php
 * foreach ($collection as $name => $result) {
 *     // $name  === 'database', 'cache', etc.
 *     // $result instanceof HealthCheckResult
 * }
 * ```
 */
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
     * Returns true when every check in the collection passed.
     * Returns false as soon as any single check reports {@see HealthCheckResult::STATUS_ERROR}.
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
