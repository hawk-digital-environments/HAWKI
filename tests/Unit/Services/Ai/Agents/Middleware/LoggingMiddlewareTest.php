<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Middleware;

use App\Services\Ai\Agents\Middleware\LoggingMiddleware;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Prompts\AgentPrompt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(LoggingMiddleware::class)]
class LoggingMiddlewareTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeLogger(): LoggerInterface&MockObject
    {
        return $this->createMock(LoggerInterface::class);
    }

    private function makeGuard(?object $user = null): Guard&MockObject
    {
        $guard = $this->createMock(Guard::class);
        $guard->method('user')->willReturn($user);
        return $guard;
    }

    private function makeAuthFactory(?object $user = null): AuthFactory&MockObject
    {
        $auth = $this->createMock(AuthFactory::class);
        $auth->method('guard')->willReturn($this->makeGuard($user));
        return $auth;
    }

    private function makePrompt(string $model = 'gpt-4o', string $invocationId = 'inv-123'): AgentPrompt
    {
        return new AgentPrompt(
            agent: $this->createMock(Agent::class),
            prompt: 'Test prompt',
            attachments: [],
            provider: $this->createMock(TextProvider::class),
            model: $model,
            invocationId: $invocationId,
        );
    }

    /**
     * Creates a RequestException mock that bypasses the constructor so we do not have
     * to build a full Illuminate HTTP Response stack. The $response property is set
     * directly since it is a public non-readonly property on RequestException.
     *
     * The mock response also carries a real TransferStats instance (GuzzleHttp\TransferStats
     * is final and cannot be mocked) so the `$response->transferStats->getEffectiveUri()`
     * access in LoggingMiddleware does not throw a null-dereference error.
     */
    private function makeRequestException(string $responseBody = 'error body'): RequestException
    {
        // GuzzleHttp\TransferStats is final, so we build a real instance with a minimal PSR-7 request.
        $psrRequest = new \GuzzleHttp\Psr7\Request('POST', 'https://example.com/api');
        $transferStats = new \GuzzleHttp\TransferStats($psrRequest);

        $response = $this->createMock(\Illuminate\Http\Client\Response::class);
        $response->method('body')->willReturn($responseBody);
        $response->method('status')->willReturn(500);
        // transferStats is a public non-readonly property — assign directly so the null-chain in
        // LoggingMiddleware (`$e->response?->transferStats->getEffectiveUri()`) resolves correctly.
        $response->transferStats = $transferStats;

        // Bypass the RequestException constructor (which calls prepareMessage → toPsrResponse)
        // and set the response property directly.
        /** @var RequestException $exception */
        $exception = $this->getMockBuilder(RequestException::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $exception->response = $response;

        return $exception;
    }

    private function makeSut(
        LoggerInterface $logger,
        AuthFactory     $auth,
    ): LoggingMiddleware {
        $sut = new LoggingMiddleware();
        $sut->useServiceContainerFallback(false);
        $sut->setService(LoggerInterface::class, $logger);
        $sut->setService(AuthFactory::class, $auth);
        return $sut;
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new LoggingMiddleware();
        static::assertInstanceOf(LoggingMiddleware::class, $sut);
    }

    // =========================================================================
    // handle — happy path
    // =========================================================================

    public function testItLogsInfoBeforeSendingPrompt(): void
    {
        $logger = $this->makeLogger();
        $infoCalls = [];
        $logger->method('info')->willReturnCallback(function (string $msg) use (&$infoCalls) {
            $infoCalls[] = $msg;
        });

        $sut = $this->makeSut($logger, $this->makeAuthFactory());
        $sut->handle($this->makePrompt(), fn($p) => new \stdClass());

        static::assertContains('Sending prompt to agent', $infoCalls);
    }

    public function testItLogsInfoAfterReceivingResponse(): void
    {
        $logger = $this->makeLogger();

        $infoCalls = [];
        $logger->method('info')->willReturnCallback(function (string $msg) use (&$infoCalls) {
            $infoCalls[] = $msg;
        });

        $sut = $this->makeSut($logger, $this->makeAuthFactory());
        $sut->handle($this->makePrompt(), fn($p) => new \stdClass());

        static::assertContains('Received response from agent', $infoCalls);
    }

    public function testItReturnsResultFromNextClosure(): void
    {
        $expected = new \stdClass();
        $expected->value = 'the result';

        $sut = $this->makeSut($this->makeLogger(), $this->makeAuthFactory());
        $result = $sut->handle($this->makePrompt(), fn($p) => $expected);

        static::assertSame($expected, $result);
    }

    public function testItIncludesModelInLogData(): void
    {
        $logger = $this->makeLogger();
        $captured = [];
        $logger->method('info')->willReturnCallback(function (string $msg, array $data) use (&$captured) {
            $captured[] = $data;
        });

        $sut = $this->makeSut($logger, $this->makeAuthFactory());
        $sut->handle($this->makePrompt('gpt-4o'), fn($p) => null);

        $hasModel = array_filter($captured, fn(array $d) => ($d['model'] ?? null) === 'gpt-4o');
        static::assertNotEmpty($hasModel);
    }

    public function testItIncludesUserIdInLogDataWhenAuthenticated(): void
    {
        $user = new \stdClass();
        $user->id = 42;

        $logger = $this->makeLogger();
        $captured = [];
        $logger->method('info')->willReturnCallback(function (string $msg, array $data) use (&$captured) {
            $captured[] = $data;
        });

        $sut = $this->makeSut($logger, $this->makeAuthFactory($user));
        $sut->handle($this->makePrompt(), fn($p) => null);

        $hasUserId = array_filter($captured, fn(array $d) => ($d['user_id'] ?? 'missing') === 42);
        static::assertNotEmpty($hasUserId);
    }

    public function testItIncludesNullUserIdWhenNotAuthenticated(): void
    {
        $logger = $this->makeLogger();
        $captured = [];
        $logger->method('info')->willReturnCallback(function (string $msg, array $data) use (&$captured) {
            $captured[] = $data;
        });

        $sut = $this->makeSut($logger, $this->makeAuthFactory(null));
        $sut->handle($this->makePrompt(), fn($p) => null);

        $hasNullUser = array_filter($captured, fn(array $d) => array_key_exists('user_id', $d) && $d['user_id'] === null);
        static::assertNotEmpty($hasNullUser);
    }

    // =========================================================================
    // handle — generic Throwable
    // =========================================================================

    public function testItLogsErrorAndRethrowsGenericThrowable(): void
    {
        $exception = new \RuntimeException('Something broke');

        $logger = $this->makeLogger();
        $logger->expects(static::once())
            ->method('error')
            ->with('Error sending prompt to agent', static::callback(
                fn(array $data) => ($data['exception'] ?? null) === $exception
            ));

        $sut = $this->makeSut($logger, $this->makeAuthFactory());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Something broke');

        $sut->handle($this->makePrompt(), fn($p) => throw $exception);
    }

    public function testItRethrowsGenericThrowableUnchanged(): void
    {
        $original = new \LogicException('logic error');
        $sut = $this->makeSut($this->makeLogger(), $this->makeAuthFactory());

        $caught = null;
        try {
            $sut->handle($this->makePrompt(), fn($p) => throw $original);
        } catch (\LogicException $e) {
            $caught = $e;
        }

        static::assertSame($original, $caught);
    }

    // =========================================================================
    // handle — RequestException (HTTP-level provider error)
    // =========================================================================

    public function testItLogsErrorWithResponseBodyOnRequestException(): void
    {
        $requestException = $this->makeRequestException('{"error":"bad request"}');

        $logger = $this->makeLogger();
        $logger->expects(static::once())
            ->method('error')
            ->with('RequestException sending prompt to agent', static::callback(
                fn(array $data) => isset($data['response']) && str_contains($data['response'], 'bad request')
            ));

        $sut = $this->makeSut($logger, $this->makeAuthFactory());

        $this->expectException(RequestException::class);

        $sut->handle($this->makePrompt(), fn($p) => throw $requestException);
    }

    public function testItTruncatesLongResponseBodyTo5000Characters(): void
    {
        $longBody = str_repeat('x', 6000);
        $requestException = $this->makeRequestException($longBody);

        $logger = $this->makeLogger();
        $logger->expects(static::once())
            ->method('error')
            ->with(static::anything(), static::callback(function (array $data) {
                $body = $data['response'] ?? '';
                return strlen($body) <= 5000 + strlen('... [truncated]')
                    && str_ends_with($body, '... [truncated]');
            }));

        $sut = $this->makeSut($logger, $this->makeAuthFactory());

        try {
            $sut->handle($this->makePrompt(), fn($p) => throw $requestException);
        } catch (RequestException) {
            // expected — we only care about the log call above
        }
    }

    public function testItRethrowsRequestExceptionUnchanged(): void
    {
        $requestException = $this->makeRequestException();
        $sut = $this->makeSut($this->makeLogger(), $this->makeAuthFactory());

        $caught = null;
        try {
            $sut->handle($this->makePrompt(), fn($p) => throw $requestException);
        } catch (RequestException $e) {
            $caught = $e;
        }

        static::assertSame($requestException, $caught);
    }
}
