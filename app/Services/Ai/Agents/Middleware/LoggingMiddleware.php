<?php

declare(strict_types=1);

namespace App\Services\Ai\Agents\Middleware;

use App\Services\System\Container\ServiceLocatorTrait;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Prompts\AgentPrompt;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Laravel AI middleware that logs every agent prompt dispatch and its outcome.
 *
 * Registered automatically for all {@see AbstractTextGeneratingAgent} subclasses via
 * {@see AbstractTextGeneratingAgent::middleware()}.
 *
 * On every request the middleware logs an `info` entry containing the model slug, provider
 * class, agent class, invocation ID, and the ID of the currently authenticated user. A second
 * `info` entry is logged on success.
 *
 * On failure two error cases are distinguished:
 * - {@see RequestException} — an HTTP-level error from the provider gateway. The response body
 *   is captured (truncated to 5 000 characters) and the effective request URL is included so
 *   the log entry is actionable without replaying the request.
 * - Any other {@see \Throwable} — logged with the exception for stack-trace visibility.
 *
 * In both error cases the exception is re-thrown so normal error handling continues.
 *
 * Uses {@see ServiceLocatorTrait} instead of constructor injection so instances stay
 * constructible without arguments. Logging is pure observability: when the service locator
 * cannot resolve a dependency (PHPUnit strict mode disables the container fallback), the
 * middleware degrades to a {@see NullLogger} and an unknown user instead of failing the
 * generation request — locally registered services (unit tests) still take precedence.
 */
class LoggingMiddleware
{
    use ServiceLocatorTrait;

    public function handle(AgentPrompt $prompt, \Closure $next)
    {
        $logger = $this->resolveService(LoggerInterface::class, new NullLogger());
        $currentUser = $this->resolveService(Factory::class, null)?->guard()->user();

        $logData = [
            'model' => $prompt->model,
            'provider' => \get_class($prompt->provider),
            'agent' => \get_class($prompt->agent),
            'invocation_id' => $prompt->invocationId,
            'user_id' => $currentUser?->id,
        ];
        $logger->info('Sending prompt to agent', $logData);

        try {
            $res = $next($prompt);
            $logger->info('Received response from agent', [
                ...$logData,
            ]);

            return $res;
        } catch (RequestException $e) {
            $responseText = $e->response->body() ?: 'no response body';

            // Truncate very large bodies so a single failed request cannot flood the log storage.
            if (mb_strlen($responseText) > 5000) {
                $responseText = mb_substr($responseText, 0, 5000) . '... [truncated]';
            }

            $logger->error('RequestException sending prompt to agent', [
                ...$logData,
                'url' => (string) ($e->response->transferStats?->getEffectiveUri() ?? 'unknown'),
                'response' => $responseText,
                'exception' => $e,
            ]);

            throw $e;
        } catch (\Throwable $e) {
            $logger->error('Error sending prompt to agent', [
                ...$logData,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * Resolves a service through the locator, degrading to $fallback when the locator
     * cannot resolve it (PHPUnit strict mode disables the container fallback). Locally
     * registered services always win, so unit-test mocks are unaffected.
     */
    private function resolveService(string $id, mixed $fallback): mixed
    {
        try {
            return $this->getService($id);
        } catch (\App\Services\System\Container\Exceptions\ServiceLocatorException) {
            return $fallback;
        }
    }
}
