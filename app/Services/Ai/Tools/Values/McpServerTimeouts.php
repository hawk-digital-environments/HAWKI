<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\Values;


use App\Casts\Contracts\CastableInstanceInterface;

/**
 * Immutable value object that holds per-server MCP transport timeout configuration.
 *
 * All three timeout values are optional — null means "use the transport default". The object
 * is stored in the `mcp_servers.timeouts` column as a JSON string via the {@see AsInstance}
 * cast and reconstructed via {@see fromArray()}.
 *
 * Serialisation keys are intentionally short (`read`, `connect`, `sse_idle`) to keep the
 * stored JSON compact. {@see McpClientFactory} reads these values and forwards them to the
 * underlying HTTP transport options accepted by `mcp/mcp-php`.
 *
 * Usage:
 * ```php
 * $timeouts = new McpServerTimeouts(readTimeout: 30.0, connectionTimeout: 5.0);
 * // Stored as: {"read":30,"connect":5}
 *
 * $restored = McpServerTimeouts::fromArray(['read' => 30, 'connect' => 5]);
 * ```
 */
final class McpServerTimeouts implements CastableInstanceInterface, \JsonSerializable
{
    private const string READ_KEY = 'read';
    private const string CONNECT_KEY = 'connect';
    private const string SSE_IDLE_KEY = 'sse_idle';

    public function __construct(
        public float|null $readTimeout = null,
        public float|null $connectionTimeout = null,
        public float|null $sseIdleTimeout = null
    )
    {
    }

    /**
     * Reconstructs an instance from the array produced by {@see toArray()}.
     * Missing keys are treated as null (use transport default).
     */
    public static function fromArray(array $data): static
    {
        return new self(
            readTimeout: isset($data[self::READ_KEY]) ? (float)$data[self::READ_KEY] : null,
            connectionTimeout: isset($data[self::CONNECT_KEY]) ? (float)$data[self::CONNECT_KEY] : null,
            sseIdleTimeout: isset($data[self::SSE_IDLE_KEY]) ? (float)$data[self::SSE_IDLE_KEY] : null
        );
    }

    /**
     * Serialises only the non-null timeout values so that the stored JSON stays minimal.
     * Null values are omitted rather than stored as JSON nulls to distinguish
     * "not configured" from "explicitly set to zero".
     */
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
