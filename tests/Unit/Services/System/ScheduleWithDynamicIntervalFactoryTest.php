<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System;

use App\Services\System\ScheduleWithDynamicIntervalFactory;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule as ScheduleService;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(ScheduleWithDynamicIntervalFactory::class)]
class ScheduleWithDynamicIntervalFactoryTest extends TestCase
{
    private LoggerInterface $logger;
    private ScheduleWithDynamicIntervalFactory $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->sut = new ScheduleWithDynamicIntervalFactory($this->logger);
    }

    private function swapScheduleMock(Event $event, string|null $expectCommand = null, array|null $expectParams = null): void
    {
        $mock = $this->createMock(ScheduleService::class);
        if ($expectCommand !== null) {
            $mock->expects(static::once())->method('command')->with($expectCommand, $expectParams ?? [])->willReturn($event);
        } else {
            $mock->method('command')->willReturn($event);
        }
        ScheduleFacade::swap($mock);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(ScheduleWithDynamicIntervalFactory::class, $this->sut);
    }

    // =========================================================================
    // makeJob - NEVER_INTERVAL
    // =========================================================================

    public function testItMakeJobReturnsNullForNeverInterval(): void
    {
        $result = $this->sut->makeJob('app:sync', null, ScheduleWithDynamicIntervalFactory::NEVER_INTERVAL);

        static::assertNull($result);
    }

    public function testItMakeJobDoesNotLogForNeverInterval(): void
    {
        $this->logger->expects(static::never())->method('error');

        $this->sut->makeJob('app:sync', null, ScheduleWithDynamicIntervalFactory::NEVER_INTERVAL);
    }

    // =========================================================================
    // makeJob - invalid interval
    // =========================================================================

    public function testItMakeJobReturnsNullForInvalidInterval(): void
    {
        $result = $this->sut->makeJob('app:sync', null, 'notAValidInterval');

        static::assertNull($result);
    }

    public function testItMakeJobLogsErrorForInvalidInterval(): void
    {
        $this->logger->expects(static::once())->method('error');

        $this->sut->makeJob('app:sync', null, 'notAValidInterval');
    }

    public function testItMakeJobErrorMessageContainsCommandNameForInvalidInterval(): void
    {
        $this->logger->expects(static::once())->method('error')->with(
            static::stringContains('app:sync')
        );

        $this->sut->makeJob('app:sync', null, 'notAValidInterval');
    }

    public function testItMakeJobErrorMessageContainsInvalidIntervalName(): void
    {
        $this->logger->expects(static::once())->method('error')->with(
            static::stringContains('notAValidInterval')
        );

        $this->sut->makeJob('app:sync', null, 'notAValidInterval');
    }

    // =========================================================================
    // makeJob - missing required args
    // =========================================================================

    public function testItMakeJobReturnsNullWhenRequiredArgsAreMissing(): void
    {
        // 'cron' requires one argument: the expression string
        $result = $this->sut->makeJob('app:sync', null, 'cron');

        static::assertNull($result);
    }

    public function testItMakeJobLogsErrorWhenRequiredArgsAreMissing(): void
    {
        $this->logger->expects(static::once())->method('error');

        $this->sut->makeJob('app:sync', null, 'cron');
    }

    public function testItMakeJobErrorMessageContainsCommandNameForMissingArgs(): void
    {
        $this->logger->expects(static::once())->method('error')->with(
            static::stringContains('app:sync')
        );

        $this->sut->makeJob('app:sync', null, 'cron');
    }

    public function testItMakeJobErrorMessageContainsIntervalNameForMissingArgs(): void
    {
        $this->logger->expects(static::once())->method('error')->with(
            static::stringContains('cron')
        );

        $this->sut->makeJob('app:sync', null, 'cron');
    }

    // =========================================================================
    // makeJob - valid interval (happy path)
    // =========================================================================

    public function testItMakeJobReturnsEventForValidIntervalWithNoArgs(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('hourly')->willReturnSelf();
        $this->swapScheduleMock($event, 'app:sync', []);

        $result = $this->sut->makeJob('app:sync', null, 'hourly');

        static::assertSame($event, $result);
    }

    public function testItMakeJobPassesCommandParametersToScheduler(): void
    {
        $params = ['--force', '--type=csv'];
        $event = $this->createMock(Event::class);
        $event->method('daily')->willReturnSelf();
        $this->swapScheduleMock($event, 'app:report', $params);

        $result = $this->sut->makeJob('app:report', $params, 'daily');

        static::assertSame($event, $result);
    }

    public function testItMakeJobPassesParsedIntervalArgsToIntervalMethod(): void
    {
        $event = $this->createMock(Event::class);
        $event->expects(static::once())->method('cron')->with('0 * * * *')->willReturnSelf();
        $this->swapScheduleMock($event);

        $result = $this->sut->makeJob('app:sync', null, 'cron', '0 * * * *');

        static::assertSame($event, $result);
    }

    public function testItMakeJobParsesJsonArrayIntervalArgs(): void
    {
        // '[15]' is a JSON array — decoded to [15] and spread as positional args
        $event = $this->createMock(Event::class);
        $event->expects(static::once())->method('hourlyAt')->with(15)->willReturnSelf();
        $this->swapScheduleMock($event);

        $result = $this->sut->makeJob('app:sync', null, 'hourlyAt', '[15]');

        static::assertSame($event, $result);
    }
}
