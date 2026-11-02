<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Values;


/**
 * The timeouts of an agent's provider, read from its driver configuration (the `request_timeout`
 * and `stall_timeout` keys of the provider's `adapter` settings in `model_providers.providers`).
 *
 * The Laravel AI package accepts a single `timeout` per prompt, which it passes to Guzzle. What
 * Guzzle does with it depends on the transfer:
 *
 * - **Non-streamed requests** run through the curl handler, where `timeout` limits the total
 *   duration of the request ({@see forRequest()}).
 * - **Streamed requests** (`stream => true`) run through Guzzle's PHP stream handler, where
 *   `timeout` limits how long a read may wait for data. It therefore aborts a stream that stays
 *   silent for that long, but never one that keeps sending data ({@see forStream()}). This also
 *   replaces the Laravel AI package's default of 60 seconds, which aborted silent streams after
 *   60 seconds. The stream handler requires `allow_url_fopen` (the PHP default); without it,
 *   Guzzle serves streams through curl, where `timeout` would be a hard total limit — the
 *   prompt timeout is therefore disabled in that case and stall detection runs through the
 *   curl low-speed options of the stall-aware gateways instead.
 *
 * Values that are missing, negative or not numeric (e.g. an empty env variable) fall back to the
 * defaults.
 */
readonly class AgentTimeouts
{
    public const int DEFAULT_REQUEST_TIMEOUT = 60;

    /**
     * Fallback for streams of providers without stall detection: no limit. A Guzzle timeout of 0
     * does not mean "no limit" for streams, it falls back to PHP's `default_socket_timeout`
     * (usually 60 seconds).
     */
    public const int DISABLED_STALL_READ_TIMEOUT = 86400;

    /**
     * @param int $requestTimeout Total time in seconds of a non-streamed request, 0 for no limit.
     * @param int $stallTimeout   Time in seconds a stream may stay silent, 0 to disable stall detection.
     */
    public function __construct(
        public int $requestTimeout = self::DEFAULT_REQUEST_TIMEOUT,
        public int $stallTimeout = 0,
    )
    {
    }

    /**
     * Creates the timeouts from the `request_timeout` and `stall_timeout` keys of a driver
     * configuration. Without the keys, providers get the 60 seconds request default and no stall
     * detection.
     */
    public static function fromDriverConfig(array $config): self
    {
        return new self(
            self::normalize($config['request_timeout'] ?? null, self::DEFAULT_REQUEST_TIMEOUT),
            self::normalize($config['stall_timeout'] ?? null, 0),
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
        // When the PHP stream handler is unavailable, Guzzle serves streams through curl,
        // where `timeout` would be a hard total limit for the whole stream — don't impose
        // one. Stall detection runs through the curl low-speed options of the stall-aware
        // gateways in that case.
        if (!$this->streamHandlerUsed()) {
            return 0;
        }

        return $this->stallTimeout === 0 ? self::DISABLED_STALL_READ_TIMEOUT : $this->stallTimeout;
    }

    /**
     * True when Guzzle serves streamed requests through its PHP stream handler (the default,
     * requires `allow_url_fopen`). Overridable for tests — the ini setting is
     * `PHP_INI_SYSTEM` and cannot be changed at runtime.
     */
    protected function streamHandlerUsed(): bool
    {
        return (bool)ini_get('allow_url_fopen');
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
