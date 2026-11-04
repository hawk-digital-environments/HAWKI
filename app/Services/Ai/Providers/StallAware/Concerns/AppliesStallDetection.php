<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\StallAware\Concerns;


use Illuminate\Http\Client\PendingRequest;
use Laravel\Ai\Providers\Provider;

/**
 * Adds stall detection to the HTTP client of a Laravel AI gateway.
 *
 * Guzzle uses different handlers for different requests: the curl handler by default, and the
 * PHP stream handler for requests with the `stream` option, which the Laravel AI package sets
 * for streaming requests. Each handler needs its own stall mechanism, so both are applied:
 *
 * - curl handler: a transfer is aborted when it receives less than one byte per second for the
 *   configured number of seconds (curl's CURLOPT_LOW_SPEED_LIMIT / CURLOPT_LOW_SPEED_TIME).
 * - stream handler: a read is aborted when it receives no data for the configured number of
 *   seconds (Guzzle's `read_timeout`). The prompt timeout of streamed requests carries the same
 *   limit as a per-read idle timeout, see
 *   {@see \App\Services\Ai\Agents\Values\AgentTimeouts::forStream()}.
 *
 * This protects against model connections that hang silently, e.g. a reasoning model that never
 * starts to stream. The stall timeout is read from the provider's driver configuration when the
 * gateway builds the HTTP client for a request (providers opt in via the `stall_timeout` key in
 * the `adapter` settings of their `model_providers.providers` entry). Because the options are
 * applied at client construction time, they also cover requests the Laravel AI package makes
 * lazily, e.g. while a stream is iterated.
 */
trait AppliesStallDetection
{
    /**
     * Returns the given HTTP client with the provider's stall detection options applied.
     *
     * Stall detection is skipped when the provider has no `stall_timeout` configured or when it
     * is set to 0 (disabled).
     */
    protected function withStallDetection(PendingRequest $client, Provider $provider): PendingRequest
    {
        $stallSeconds = (int)($provider->additionalConfiguration()['stall_timeout'] ?? 0);

        if ($stallSeconds <= 0) {
            return $client;
        }

        return $client->withOptions([
            'curl' => [
                CURLOPT_LOW_SPEED_LIMIT => 1,
                CURLOPT_LOW_SPEED_TIME => $stallSeconds,
            ],
            'read_timeout' => $stallSeconds,
        ]);
    }
}
