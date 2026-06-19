<?php
declare(strict_types=1);


namespace App\Services\Ai\Values;


use App\Casts\Contracts\CastableInstanceInterface;

class McpServerTimeouts implements CastableInstanceInterface, \JsonSerializable
{
    private const READ_KEY = 'read';
    private const CONNECT_KEY = 'connect';
    private const SSE_IDLE_KEY = 'sse_idle';

    public function __construct(
        public float|null $readTimeout = null,
        public float|null $connectionTimeout = null,
        public float|null $sseIdleTimeout = null
    )
    {
    }

    public static function fromArray(array $data): static
    {
        return new static(
            readTimeout: isset($data[self::READ_KEY]) ? (float)$data[self::READ_KEY] : null,
            connectionTimeout: isset($data[self::CONNECT_KEY]) ? (float)$data[self::CONNECT_KEY] : null,
            sseIdleTimeout: isset($data[self::SSE_IDLE_KEY]) ? (float)$data[self::SSE_IDLE_KEY] : null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            self::READ_KEY => $this->readTimeout,
            self::CONNECT_KEY => $this->connectionTimeout,
            self::SSE_IDLE_KEY => $this->sseIdleTimeout
        ], static fn($value) => $value !== null);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
