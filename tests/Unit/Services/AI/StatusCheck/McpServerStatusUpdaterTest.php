<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\StatusCheck;

use App\Models\Ai\McpServer;
use App\Services\Ai\Repositories\McpServerRepository;
use App\Services\Ai\StatusCheck\Events\McpServerStatusCheckedEvent;
use App\Services\Ai\StatusCheck\Events\McpServerStatusCheckFailedEvent;
use App\Services\Ai\StatusCheck\Events\McpStatusCheckCompletedEvent;
use App\Services\Ai\StatusCheck\Events\McpStatusCheckStartingEvent;
use App\Services\Ai\StatusCheck\McpServerStatusUpdater;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Services\Ai\Tools\Mcp\McpClientFactory;
use App\Services\Ai\Values\OnlineStatus;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(McpServerStatusUpdater::class)]
class McpServerStatusUpdaterTest extends TestCase
{
    private McpServerRepository&MockObject $mcpServerRepository;
    private McpClientFactory&MockObject $mcpClientFactory;
    private LoggerInterface&MockObject $logger;
    private McpServerStatusUpdater $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mcpServerRepository = $this->createMock(McpServerRepository::class);
        $this->mcpClientFactory = $this->createMock(McpClientFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new McpServerStatusUpdater(
            $this->mcpServerRepository,
            $this->mcpClientFactory,
            $this->logger
        );
    }

    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(McpServerStatusUpdater::class, $this->sut);
    }

    // =========================================================================
    // Repository failure
    // =========================================================================

    public function testItReturnsEmptyMetricsWhenRepositoryThrows(): void
    {
        $this->mcpServerRepository
            ->method('findAll')
            ->willThrowException(new \RuntimeException('DB connection failed'));

        $result = $this->sut->run();

        static::assertSame(0, $result->get(McpServerStatusUpdater::METRIC_SERVER_COUNT));
        static::assertSame(0, $result->get(McpServerStatusUpdater::METRIC_SERVER_ONLINE));
        static::assertSame(0, $result->get(McpServerStatusUpdater::METRIC_SERVER_OFFLINE));
    }

    public function testItDoesNotDispatchAnyEventsWhenRepositoryThrows(): void
    {
        Event::fake();
        $this->mcpServerRepository
            ->method('findAll')
            ->willThrowException(new \RuntimeException('DB connection failed'));

        $this->sut->run();

        Event::assertNothingDispatched();
    }

    // =========================================================================
    // Successful ping
    // =========================================================================

    public function testItMarksServerOnlineWhenPingSucceeds(): void
    {
        $server = $this->makeServer('https://online.example.com');
        $client = $this->onlinePingClient();

        $this->mcpServerRepository->method('findAll')->willReturn(new \Illuminate\Database\Eloquent\Collection([$server]));
        $this->mcpClientFactory->method('createForServer')->willReturn($client);

        $this->mcpServerRepository
            ->expects(static::once())
            ->method('setOnlineStatus')
            ->with($server, OnlineStatus::ONLINE);

        $this->sut->run();
    }

    public function testItIncrementsOnlineAndCountCountersWhenPingSucceeds(): void
    {
        $server = $this->makeServer('https://online.example.com');

        $this->mcpServerRepository->method('findAll')->willReturn(new \Illuminate\Database\Eloquent\Collection([$server]));
        $this->mcpClientFactory->method('createForServer')->willReturn($this->onlinePingClient());

        $metrics = $this->sut->run();

        static::assertSame(1, $metrics->get(McpServerStatusUpdater::METRIC_SERVER_ONLINE));
        static::assertSame(0, $metrics->get(McpServerStatusUpdater::METRIC_SERVER_OFFLINE));
        static::assertSame(1, $metrics->get(McpServerStatusUpdater::METRIC_SERVER_COUNT));
    }

    // =========================================================================
    // Failed ping (returns false)
    // =========================================================================

    public function testItMarksServerOfflineWhenPingFails(): void
    {
        $server = $this->makeServer('https://offline.example.com');
        $client = $this->offlinePingClient();

        $this->mcpServerRepository->method('findAll')->willReturn(new \Illuminate\Database\Eloquent\Collection([$server]));
        $this->mcpClientFactory->method('createForServer')->willReturn($client);

        $this->mcpServerRepository
            ->expects(static::once())
            ->method('setOnlineStatus')
            ->with($server, OnlineStatus::OFFLINE);

        $this->sut->run();
    }

    public function testItIncrementsOfflineAndCountCountersWhenPingFails(): void
    {
        $server = $this->makeServer('https://offline.example.com');

        $this->mcpServerRepository->method('findAll')->willReturn(new \Illuminate\Database\Eloquent\Collection([$server]));
        $this->mcpClientFactory->method('createForServer')->willReturn($this->offlinePingClient());

        $metrics = $this->sut->run();

        static::assertSame(0, $metrics->get(McpServerStatusUpdater::METRIC_SERVER_ONLINE));
        static::assertSame(1, $metrics->get(McpServerStatusUpdater::METRIC_SERVER_OFFLINE));
        static::assertSame(1, $metrics->get(McpServerStatusUpdater::METRIC_SERVER_COUNT));
    }

    // =========================================================================
    // Exception during server check
    // =========================================================================

    public function testItMarksServerOfflineWhenExceptionIsThrown(): void
    {
        $server = $this->makeServer('https://error.example.com');

        $this->mcpServerRepository->method('findAll')->willReturn(new \Illuminate\Database\Eloquent\Collection([$server]));
        $this->mcpClientFactory->method('createForServer')
            ->willThrowException(new \RuntimeException('Timeout'));

        $this->mcpServerRepository
            ->expects(static::once())
            ->method('setOnlineStatus')
            ->with($server, OnlineStatus::OFFLINE);

        $this->sut->run();
    }

    public function testItIncrementsOfflineCounterWhenExceptionIsThrown(): void
    {
        $server = $this->makeServer('https://error.example.com');

        $this->mcpServerRepository->method('findAll')->willReturn(new \Illuminate\Database\Eloquent\Collection([$server]));
        $this->mcpClientFactory->method('createForServer')
            ->willThrowException(new \RuntimeException('Timeout'));

        $metrics = $this->sut->run();

        // 'count' is only incremented after a successful ping attempt; exceptions bypass it
        static::assertSame(1, $metrics->get(McpServerStatusUpdater::METRIC_SERVER_OFFLINE));
        static::assertSame(0, $metrics->get(McpServerStatusUpdater::METRIC_SERVER_COUNT));
    }

    public function testItRecordsErrorWhenExceptionIsThrown(): void
    {
        $server = $this->makeServer('https://error.example.com');

        $this->mcpServerRepository->method('findAll')->willReturn(new \Illuminate\Database\Eloquent\Collection([$server]));
        $this->mcpClientFactory->method('createForServer')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $metrics = $this->sut->run();

        static::assertTrue($metrics->hasErrors());
    }

    public function testItRecordsFormattedErrorMessageWhenExceptionIsThrown(): void
    {
        $server = $this->makeServer('https://error.example.com');

        $this->mcpServerRepository->method('findAll')->willReturn(new \Illuminate\Database\Eloquent\Collection([$server]));
        $this->mcpClientFactory->method('createForServer')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $metrics = $this->sut->run();

        $errors = iterator_to_array($metrics->getErrors());
        static::assertSame(
            'Error checking status of MCP server https://error.example.com: Connection refused, marking as OFFLINE',
            $errors[0]
        );
    }

    public function testItContinuesProcessingRemainingServersAfterOneThrows(): void
    {
        $failingServer = $this->makeServer('https://failing.example.com');
        $successServer = $this->makeServer('https://success.example.com');

        $this->mcpServerRepository->method('findAll')
            ->willReturn(new \Illuminate\Database\Eloquent\Collection([$failingServer, $successServer]));

        $this->mcpClientFactory
            ->method('createForServer')
            ->willReturnCallback(function (McpServer $server) use ($failingServer) {
                if ($server === $failingServer) {
                    throw new \RuntimeException('Timeout');
                }
                return $this->onlinePingClient();
            });

        $metrics = $this->sut->run();

        static::assertSame(1, $metrics->get(McpServerStatusUpdater::METRIC_SERVER_ONLINE));
        static::assertSame(1, $metrics->get(McpServerStatusUpdater::METRIC_SERVER_OFFLINE));
        static::assertSame(1, $metrics->get(McpServerStatusUpdater::METRIC_SERVER_COUNT));
    }

    // =========================================================================
    // Event dispatching
    // =========================================================================

    public function testItDispatchesStartingEventBeforeProcessingServers(): void
    {
        Event::fake();
        $this->mcpServerRepository->method('findAll')->willReturn(new \Illuminate\Database\Eloquent\Collection());

        $this->sut->run();

        Event::assertDispatched(McpStatusCheckStartingEvent::class);
    }

    public function testItDispatchesCompletedEventAfterAllServersProcessed(): void
    {
        Event::fake();
        $this->mcpServerRepository->method('findAll')->willReturn(new \Illuminate\Database\Eloquent\Collection());

        $this->sut->run();

        Event::assertDispatched(McpStatusCheckCompletedEvent::class);
    }

    public function testItPassesTheSameMetricsInstanceToStartingAndCompletedEvents(): void
    {
        Event::fake();
        $this->mcpServerRepository->method('findAll')->willReturn(new \Illuminate\Database\Eloquent\Collection());

        $metrics = $this->sut->run();

        Event::assertDispatched(
            McpStatusCheckStartingEvent::class,
            fn(McpStatusCheckStartingEvent $e) => $e->metrics === $metrics
        );
        Event::assertDispatched(
            McpStatusCheckCompletedEvent::class,
            fn(McpStatusCheckCompletedEvent $e) => $e->metrics === $metrics
        );
    }

    public function testItDispatchesCheckedEventAfterEachPing(): void
    {
        Event::fake();
        $server = $this->makeServer('https://example.com');
        $client = $this->onlinePingClient();

        $this->mcpServerRepository->method('findAll')->willReturn(new \Illuminate\Database\Eloquent\Collection([$server]));
        $this->mcpClientFactory->method('createForServer')->willReturn($client);

        $this->sut->run();

        Event::assertDispatched(
            McpServerStatusCheckedEvent::class,
            fn(McpServerStatusCheckedEvent $e) => $e->server === $server && $e->client === $client
        );
    }

    public function testItDispatchesCheckedEventWhenPingReturnsFalse(): void
    {
        Event::fake();
        $server = $this->makeServer('https://offline.example.com');

        $this->mcpServerRepository->method('findAll')->willReturn(new \Illuminate\Database\Eloquent\Collection([$server]));
        $this->mcpClientFactory->method('createForServer')->willReturn($this->offlinePingClient());

        $this->sut->run();

        Event::assertDispatched(
            McpServerStatusCheckedEvent::class,
            fn(McpServerStatusCheckedEvent $e) => $e->server === $server
        );
    }

    public function testItDispatchesFailedEventWhenServerThrowsException(): void
    {
        Event::fake();
        $server = $this->makeServer('https://failing.example.com');
        $exception = new \RuntimeException('Connection refused');

        $this->mcpServerRepository->method('findAll')->willReturn(new \Illuminate\Database\Eloquent\Collection([$server]));
        $this->mcpClientFactory->method('createForServer')->willThrowException($exception);

        $this->sut->run();

        Event::assertDispatched(
            McpServerStatusCheckFailedEvent::class,
            fn(McpServerStatusCheckFailedEvent $e) => $e->server === $server
                && $e->exception === $exception
        );
    }

    public function testItDoesNotDispatchCheckedEventWhenServerThrowsException(): void
    {
        Event::fake();
        $server = $this->makeServer('https://failing.example.com');

        $this->mcpServerRepository->method('findAll')->willReturn(new \Illuminate\Database\Eloquent\Collection([$server]));
        $this->mcpClientFactory->method('createForServer')
            ->willThrowException(new \RuntimeException('Timeout'));

        $this->sut->run();

        Event::assertNotDispatched(McpServerStatusCheckedEvent::class);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeServer(string $url): McpServer
    {
        $server = new McpServer();
        $server->url = $url;
        $server->status = OnlineStatus::UNKNOWN;
        return $server;
    }

    private function onlinePingClient(): HawkiMcpClient&MockObject
    {
        $client = $this->createMock(HawkiMcpClient::class);
        $client->method('ping')->willReturn(true);
        return $client;
    }

    private function offlinePingClient(): HawkiMcpClient&MockObject
    {
        $client = $this->createMock(HawkiMcpClient::class);
        $client->method('ping')->willReturn(false);
        return $client;
    }
}
