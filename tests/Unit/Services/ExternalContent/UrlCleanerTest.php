<?php
declare(strict_types=1);

namespace Tests\Unit\Services\ExternalContent;

use App\Services\ExternalContent\UrlCleaner;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(UrlCleaner::class)]
class UrlCleanerTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeClient(array $responses): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        return new Client(['handler' => $stack]);
    }

    private function makeSut(Client $client = null, LoggerInterface $logger = null): UrlCleaner
    {
        return new UrlCleaner(
            logger: $logger ?? $this->createMock(LoggerInterface::class),
            client: $client ?? $this->makeClient([new GuzzleResponse(200)]),
        );
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new UrlCleaner($this->createMock(LoggerInterface::class));
        static::assertInstanceOf(UrlCleaner::class, $sut);
    }

    // =========================================================================
    // cleanMany — empty input
    // =========================================================================

    public function testItCleanManyReturnsEmptyArrayForEmptyInput(): void
    {
        $sut = $this->makeSut();
        static::assertSame([], $sut->cleanMany([]));
    }

    // =========================================================================
    // cleanMany — tracking parameter stripping
    // =========================================================================

    #[DataProvider('provideTestItStripsTrackingParametersData')]
    public function testItStripsTrackingParameters(string $input, string $expected): void
    {
        $sut = $this->makeSut($this->makeClient([new GuzzleResponse(200)]));
        static::assertSame($expected, $sut->clean($input));
    }

    public static function provideTestItStripsTrackingParametersData(): iterable
    {
        yield 'utm_source' => [
            'https://example.com/?utm_source=google&keep=this',
            'https://example.com/?keep=this',
        ];
        yield 'utm_medium' => [
            'https://example.com/?utm_medium=cpc',
            'https://example.com/',
        ];
        yield 'utm_campaign' => [
            'https://example.com/?utm_campaign=sale',
            'https://example.com/',
        ];
        yield 'utm_term' => [
            'https://example.com/?utm_term=shoes',
            'https://example.com/',
        ];
        yield 'utm_content' => [
            'https://example.com/?utm_content=banner',
            'https://example.com/',
        ];
        yield 'gclid' => [
            'https://example.com/?gclid=abc123&keep=this',
            'https://example.com/?keep=this',
        ];
        yield 'fbclid' => [
            'https://example.com/?fbclid=xyz789',
            'https://example.com/',
        ];
        yield 'all tracking params combined' => [
            'https://example.com/?utm_source=a&utm_medium=b&utm_campaign=c&utm_term=d&utm_content=e&gclid=f&fbclid=g&keep=this',
            'https://example.com/?keep=this',
        ];
        yield 'non-tracking params are preserved' => [
            'https://example.com/?page=2&sort=asc',
            'https://example.com/?page=2&sort=asc',
        ];
    }

    // =========================================================================
    // cleanMany — redirect following
    // =========================================================================

    public function testItFollowsRedirectAndStripsTrackingParametersFromFinalUrl(): void
    {
        $sut = $this->makeSut($this->makeClient([
            new GuzzleResponse(301, ['Location' => 'https://final.example.com/?keep=this&fbclid=123']),
            new GuzzleResponse(200),
        ]));

        $result = $sut->clean('https://short.example.com/');

        static::assertSame('https://final.example.com/?keep=this', $result);
    }

    // =========================================================================
    // cleanMany — failure handling
    // =========================================================================

    public function testItLogsWarningOnRequestFailure(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('warning')
            ->with(
                static::stringContains('failed to resolve URL redirect chain'),
                static::arrayHasKey('url')
            );

        $sut = $this->makeSut(
            $this->makeClient([
                new ConnectException(
                    'Connection refused',
                    new GuzzleRequest('HEAD', 'https://example.com/')
                ),
            ]),
            $logger
        );

        $sut->clean('https://example.com/');
    }

    public function testItReturnsCleansedInputUrlOnRequestFailure(): void
    {
        $sut = $this->makeSut($this->makeClient([
            new ConnectException(
                'Connection refused',
                new GuzzleRequest('HEAD', 'https://example.com/?utm_source=gone')
            ),
        ]));

        // Falls back to the input URL with tracking params stripped (no redirect followed)
        static::assertSame('https://example.com/', $sut->clean('https://example.com/?utm_source=gone'));
    }

    // =========================================================================
    // cleanMany — concurrent batch
    // =========================================================================

    public function testItCleansManyUrlsConcurrently(): void
    {
        $sut = $this->makeSut($this->makeClient([
            new GuzzleResponse(200),
            new GuzzleResponse(200),
        ]));

        $result = $sut->cleanMany([
            'https://example.com/?utm_source=a',
            'https://other.example.com/?fbclid=b&keep=yes',
        ]);

        static::assertSame([
            'https://example.com/',
            'https://other.example.com/?keep=yes',
        ], $result);
    }

    public function testItPreservesInputOrderForBatchResults(): void
    {
        $sut = $this->makeSut($this->makeClient([
            new GuzzleResponse(200),
            new GuzzleResponse(200),
            new GuzzleResponse(200),
        ]));

        $result = $sut->cleanMany([
            'https://first.example.com/',
            'https://second.example.com/',
            'https://third.example.com/',
        ]);

        static::assertSame([
            'https://first.example.com/',
            'https://second.example.com/',
            'https://third.example.com/',
        ], $result);
    }
}
