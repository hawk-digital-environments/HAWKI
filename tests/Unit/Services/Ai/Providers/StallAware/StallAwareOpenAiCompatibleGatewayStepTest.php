<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\StallAware;

use App\Services\Ai\Providers\StallAware\Concerns\AppliesStallDetection;
use App\Services\Ai\Providers\StallAware\StallAwareOpenAiCompatibleGateway;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Providers\OpenAiCompatibleProvider;
use Laravel\Ai\Providers\Provider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;
use Tests\Unit\Services\Ai\Providers\StallAware\StallAwareOpenAiCompatibleGatewayStepTest\StallAwareOpenAiCompatibleGatewayStepTestFixtures\ClientSpyingGateway;

/**
 * Regression tests for the request paths through the stall-aware gateway.
 *
 * The streaming path is the critical one: the Laravel AI package starts the HTTP request of a
 * stream lazily, only when the returned generator is iterated ({@see \Laravel\Ai\Providers\Concerns\StreamsText::stream()}).
 * The stall options must be present on that request — they are, because the gateway applies them
 * in client() at request construction time, not through any request-scoped state.
 */
#[CoversClass(StallAwareOpenAiCompatibleGateway::class)]
#[CoversTrait(AppliesStallDetection::class)]
class StallAwareOpenAiCompatibleGatewayStepTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeProvider(): Provider
    {
        return new OpenAiCompatibleProvider(
            [
                'name' => 'test',
                'driver' => 'openai-compatible',
                'key' => 'test-key',
                'url' => 'https://api.example.com/v1',
                'stall_timeout' => 120,
            ],
            $this->app->make(Dispatcher::class)
        );
    }

    private function makeSut(): ClientSpyingGateway
    {
        return new ClientSpyingGateway($this->app->make(Dispatcher::class));
    }

    // =========================================================================
    // Non-streaming request path
    // =========================================================================

    public function testItAppliesStallDetectionToTheRequestOfATextStep(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response([
                'model' => 'test-model',
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'ok']]],
            ]),
        ]);

        $sut = $this->makeSut();

        $response = $sut->generateTextStep(
            $this->makeProvider(),
            'test-model',
            null,
            [new UserMessage('hello')],
            [],
            null,
            null,
            0,
            new StepContext()
        );

        static::assertSame('ok', $response->text);
        static::assertCount(1, $sut->capturedOptions);
        static::assertSame(1, $sut->capturedOptions[0]['curl'][CURLOPT_LOW_SPEED_LIMIT]);
        static::assertSame(120, $sut->capturedOptions[0]['curl'][CURLOPT_LOW_SPEED_TIME]);
        // text steps keep the passed total timeout — no capping outside stream steps
        static::assertSame(0, $sut->capturedOptions[0]['timeout']);
    }

    // =========================================================================
    // Streaming request path (lazy)
    // =========================================================================

    public function testItAppliesStallDetectionToTheLazilyStartedRequestOfAStreamStep(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(
                "data: {\"model\":\"test-model\",\"choices\":[{\"index\":0,\"delta\":{\"role\":\"assistant\",\"content\":\"Hello\"}}]}\n\n"
                . "data: {\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"stop\"}],\"usage\":{\"prompt_tokens\":5,\"completion_tokens\":2,\"total_tokens\":7}}\n\n"
                . "data: [DONE]\n\n",
                200,
                ['Content-Type' => 'text/event-stream']
            ),
        ]);

        $sut = $this->makeSut();

        $stream = $sut->generateStreamStep(
            'test-invocation',
            $this->makeProvider(),
            'test-model',
            null,
            [new UserMessage('hello')],
            [],
            null,
            null,
            0,
            new StepContext()
        );

        // The stream is lazy: creating it must not start an HTTP request yet. This is exactly
        // where the previous scope-based implementation lost the stall options — the request
        // only starts when the generator is iterated, long after sendStreaming() returned.
        static::assertSame([], $sut->capturedOptions);

        iterator_to_array($stream);

        static::assertCount(1, $sut->capturedOptions);
        static::assertSame(1, $sut->capturedOptions[0]['curl'][CURLOPT_LOW_SPEED_LIMIT]);
        static::assertSame(120, $sut->capturedOptions[0]['curl'][CURLOPT_LOW_SPEED_TIME]);
        // The stream handler ignores curl options, so the read timeout must ride along for
        // streamed requests — see AppliesStallDetection.
        static::assertSame(120, $sut->capturedOptions[0]['read_timeout']);
        // The prompt timeout is passed through unchanged: AbstractLaravelAgent::sendStreaming()
        // passes the stall timeout (or no limit when stall detection is off) via
        // AgentTimeouts::forStream(), which acts as the per-read idle timeout on the stream handler.
        static::assertSame(0, $sut->capturedOptions[0]['timeout']);
    }
}
