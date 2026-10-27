<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Values;


/**
 * The timeouts of an agent's provider, read from its driver configuration
 * (`model_providers.request_timeout` / `model_providers.stall_timeout`, overridable per provider
 * via its adapter settings, see {@see \App\Services\Ai\Providers\Adapters\DriverFactory}).
 *
 * The Laravel AI package accepts a single `timeout` per prompt, which it passes to Guzzle. What
 * Guzzle does with it depends on the transfer:
 *
 * - **Non-streamed requests** run through the curl handler, where `timeout` limits the total
 *   duration of the request ({@see forRequest()}).
 * - **Streamed requests** (`stream => true`) run through Guzzle's PHP stream handler, where
 *   `timeout` limits how long a single read may wait for data. It therefore aborts a stream that
 *   stays silent for that long, but never one that keeps sending data ({@see forStream()}).
 *   The stream handler ignores curl options, which is why stall detection cannot be added via
 *   `CURLOPT_LOW_SPEED_TIME`. This requires `allow_url_fopen` (the PHP default); without it,
 *   Guzzle falls back to curl and the stall timeout becomes a total limit for streams.
 *
 * Values that are missing, negative or not numeric (e.g. an empty env variable) fall back to the defaults.
 */
readonly class AgentTimeouts
{
    public const int DEFAULT_REQUEST_TIMEOUT = 60;
    public const int DEFAULT_STALL_TIMEOUT = 120;

    /**
     * Read timeout used for streams when stall detection is disabled. A Guzzle timeout of 0 does not
     * mean "no limit" for streams, it falls back to PHP's `default_socket_timeout` (usually 60 seconds).
     */
    public const int DISABLED_STALL_READ_TIMEOUT = 86400;

    /**
     * @param int $requestTimeout Total time in seconds of a non-streamed request, 0 for no limit.
     * @param int $stallTimeout   Time in seconds a stream may stay silent, 0 to disable stall detection.
     */
    public function __construct(
        public int $requestTimeout = self::DEFAULT_REQUEST_TIMEOUT,
        public int $stallTimeout = self::DEFAULT_STALL_TIMEOUT,
    )
    {
    }

    /**
     * Creates the timeouts from the `request_timeout` and `stall_timeout` keys of a driver configuration.
     */
    public static function fromDriverConfig(array $config): self
    {
        return new self(
            self::normalize($config['request_timeout'] ?? null, self::DEFAULT_REQUEST_TIMEOUT),
            self::normalize($config['stall_timeout'] ?? null, self::DEFAULT_STALL_TIMEOUT),
        );
    }

    /**
     * The timeout to pass to the Laravel AI package for a non-streamed prompt (total duration, 0 = no limit).
     */
    public function forRequest(): int
    {
        return $this->requestTimeout;
    }

    /**
     * The timeout to pass to the Laravel AI package for a streamed prompt (maximum silence between two reads).
     */
    public function forStream(): int
    {
        return $this->stallTimeout === 0 ? self::DISABLED_STALL_READ_TIMEOUT : $this->stallTimeout;
    }

    private static function normalize(mixed $value, int $default): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
            $value = (int)$value;
            return $value >= 0 ? $value : $default;
        }

        return $default;
    }
}
