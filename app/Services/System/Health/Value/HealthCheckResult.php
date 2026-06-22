<?php
declare(strict_types=1);


namespace App\Services\System\Health\Value;


/**
 * Immutable value object representing the outcome of a single infrastructure health check.
 *
 * Produced by {@see HealthChecker} for each component (database, cache, Redis, storage).
 * Collected into a {@see HealthCheckResultCollection} that summarises the overall system health.
 */
readonly class HealthCheckResult implements \JsonSerializable
{
    /** Check passed — the component is reachable and functional. */
    public const STATUS_OK = 'ok';
    /** Check failed — the component is unreachable or behaving incorrectly. */
    public const STATUS_ERROR = 'error';

    public function __construct(
        /** Unique name for this check (see `CHECK_NAME_*` constants on {@see HealthChecker}). */
        public string $checkName,
        /** One of {@see STATUS_OK} or {@see STATUS_ERROR}. */
        public string $status,
        /** Human-readable description of the check outcome. */
        public string $message,
        /** Time taken to complete the check in milliseconds, or null when not measured. */
        public ?float $responseTime = null
    )
    {
    }

    /**
     * Returns true when the check passed.
     */
    public function isOk(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    /**
     * Returns true when the check failed.
     */
    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    /**
     * Serialises the result to an array for JSON output.
     * The `response_time` key is omitted when no response time was measured.
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            'name' => $this->checkName,
            'status' => $this->status,
            'message' => $this->message,
            'response_time' => $this->responseTime
        ]);
    }
}
