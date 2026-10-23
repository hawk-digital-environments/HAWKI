<?php
declare(strict_types=1);

namespace App\Services\Ai\LaravelAi;

use Psr\Http\Message\RequestInterface;

/**
 * Guzzle middleware that adds stall detection to the HTTP requests of AI agents: a transfer is
 * aborted when it receives less than one byte per second for the configured number of seconds
 * (curl's CURLOPT_LOW_SPEED_LIMIT / CURLOPT_LOW_SPEED_TIME). This protects against model
 * connections that hang silently, e.g. a reasoning model that never starts to stream.
 *
 * The middleware is registered globally in {@see \App\Providers\AiServiceProvider::boot()}, but it
 * only changes the request options while {@see AgentRequestScope} marks an agent request as
 * running and the provider has a stall timeout configured (see `model_providers.stall_timeout`).
 * Every other outgoing HTTP request of the process stays untouched.
 */
class StallDetectionMiddleware
{
    /**
     * Wraps the given Guzzle handler.
     *
     * @param callable $handler The next handler in the stack.
     * @return \Closure(RequestInterface, array): \GuzzleHttp\Promise\PromiseInterface
     */
    public function __invoke(callable $handler): \Closure
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $stallSeconds = AgentRequestScope::stallSeconds();
            if (AgentRequestScope::isActive() && $stallSeconds !== null && $stallSeconds > 0) {
                $options['curl'] = ($options['curl'] ?? []) + [
                    CURLOPT_LOW_SPEED_LIMIT => 1,
                    CURLOPT_LOW_SPEED_TIME => $stallSeconds,
                ];
            }

            return $handler($request, $options);
        };
    }
}
