<?php
declare(strict_types=1);


namespace App\Services\System\Health\Value;


readonly class HealthCheckResult implements \JsonSerializable
{
    public const STATUS_OK = 'ok';
    public const STATUS_ERROR = 'error';

    public function __construct(
        public string $checkName,
        public string $status,
        public string $message,
        public ?float $responseTime = null
    )
    {
    }

    public function isOk(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    /**
     * @inheritDoc
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
