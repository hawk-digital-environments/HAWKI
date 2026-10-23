<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\LaravelAi;

use App\Services\Ai\LaravelAi\AgentRequestScope;
use App\Services\Ai\LaravelAi\StallDetectionMiddleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(StallDetectionMiddleware::class)]
class StallDetectionMiddlewareTest extends TestCase
{
    /**
     * Sends a request through the middleware and returns the options the handler received.
     */
    private function sendThroughMiddleware(array $options): array
    {
        $captured = null;
        $handler = function ($request, array $handlerOptions) use (&$captured) {
            $captured = $handlerOptions;
            return new FulfilledPromise(new Response());
        };

        $middleware = new StallDetectionMiddleware();
        $middleware($handler)(new Request('POST', 'https://api.example.com/v1/chat/completions'), $options);

        return $captured;
    }

    public function testItAddsStallDetectionInsideTheAgentScope(): void
    {
        $captured = null;

        AgentRequestScope::run(function () use (&$captured) {
            $captured = $this->sendThroughMiddleware(['timeout' => 60]);
        }, 120);

        static::assertSame(1, $captured['curl'][CURLOPT_LOW_SPEED_LIMIT]);
        static::assertSame(120, $captured['curl'][CURLOPT_LOW_SPEED_TIME]);
        // The middleware must not touch the other options
        static::assertSame(60, $captured['timeout']);
    }

    public function testItKeepsExistingCurlOptions(): void
    {
        $captured = null;

        AgentRequestScope::run(function () use (&$captured) {
            $captured = $this->sendThroughMiddleware(['curl' => [CURLOPT_FOLLOWLOCATION => true]]);
        }, 120);

        static::assertTrue($captured['curl'][CURLOPT_FOLLOWLOCATION]);
        static::assertSame(120, $captured['curl'][CURLOPT_LOW_SPEED_TIME]);
    }

    public function testItDoesNothingWhenStallDetectionIsDisabled(): void
    {
        $captured = null;

        AgentRequestScope::run(function () use (&$captured) {
            $captured = $this->sendThroughMiddleware(['timeout' => 60]);
        }, 0);

        static::assertArrayNotHasKey('curl', $captured);
    }

    public function testItDoesNothingWithoutAStallTimeout(): void
    {
        $captured = null;

        AgentRequestScope::run(function () use (&$captured) {
            $captured = $this->sendThroughMiddleware(['timeout' => 60]);
        });

        static::assertArrayNotHasKey('curl', $captured);
    }

    public function testItDoesNothingOutsideTheAgentScope(): void
    {
        $captured = $this->sendThroughMiddleware(['timeout' => 60]);

        static::assertArrayNotHasKey('curl', $captured);
    }
}
