<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Health;

use App\Services\System\Health\Events\HealthCheckEvent;
use App\Services\System\Health\Events\QuickHealthCheckEvent;
use App\Services\System\Health\HealthChecker;
use App\Services\System\Health\HealthTimer;
use App\Services\System\Health\Value\HealthCheckResult;
use App\Services\System\Health\Value\HealthCheckResultCollection;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Tests\TestCase;

#[CoversClass(HealthChecker::class)]
#[CoversClass(HealthCheckResultCollection::class)]
#[CoversClass(QuickHealthCheckEvent::class)]
class HealthModesTest extends TestCase
{
    public function testWarningsDegradeTheInstanceAndErrorsTakePrecedence(): void
    {
        $warning = new HealthCheckResult('policy', HealthCheckResult::STATUS_WARNING, 'Unavailable');
        $degraded = new HealthCheckResultCollection($warning);
        self::assertSame('degraded', $degraded->getStatus());
        self::assertFalse($degraded->isUnhealthy());
        $unhealthy = new HealthCheckResultCollection($warning, new HealthCheckResult('db', HealthCheckResult::STATUS_ERROR, 'Down'));
        self::assertSame('unhealthy', $unhealthy->getStatus());
    }

    public function testQuickChecksOnlyDispatchTheQuickEvent(): void
    {
        DB::shouldReceive('connection->getPdo')->once()->andReturn(null);
        $timer = Mockery::mock(HealthTimer::class);
        $timer->shouldNotReceive('markAsFailed');
        $events = new Dispatcher();
        $deepCalls = 0;
        $events->listen(HealthCheckEvent::class, static function () use (&$deepCalls) { $deepCalls++; });
        $events->listen(QuickHealthCheckEvent::class, static function (QuickHealthCheckEvent $event) {
            $event->addResult(new HealthCheckResult('policy', HealthCheckResult::STATUS_WARNING, 'Missing'));
        });
        $result = (new HealthChecker(new NullLogger(), $events, $timer))->quickCheck();
        self::assertSame('degraded', $result->getStatus());
        self::assertSame(0, $deepCalls);
    }

    public function testQuickListenerFailuresProduceAnErrorAndMarkTheTimerFailed(): void
    {
        DB::shouldReceive('connection->getPdo')->once()->andReturn(null);
        $timer = Mockery::mock(HealthTimer::class);
        $timer->shouldReceive('markAsFailed')->once();
        $events = new Dispatcher();
        $events->listen(QuickHealthCheckEvent::class, static function (): never {
            throw new \RuntimeException('Database unavailable');
        });
        $result = (new HealthChecker(new NullLogger(), $events, $timer))->quickCheck();
        self::assertSame('unhealthy', $result->getStatus());
    }

    public function testADegradedDeepCheckMarksTheTimerHealthy(): void
    {
        DB::shouldReceive('connection->getPdo')->andReturn(null);
        DB::shouldReceive('connection->select')->andReturn([]);
        Cache::shouldReceive('put')->once();
        Cache::shouldReceive('get')->once()->andReturn('test_value');
        Cache::shouldReceive('forget')->once();
        Redis::shouldReceive('ping')->once();
        $timer = Mockery::mock(HealthTimer::class);
        $timer->shouldReceive('markAsHealthy')->once();
        $timer->shouldNotReceive('markAsFailed');
        $events = new Dispatcher();
        $events->listen(HealthCheckEvent::class, static function (HealthCheckEvent $event) {
            $event->addResult(new HealthCheckResult('policy', HealthCheckResult::STATUS_WARNING, 'Missing'));
            // Storage permissions are an environment property, independent of this timer test.
            $event->addResult(new HealthCheckResult('storage', HealthCheckResult::STATUS_OK, 'Test storage'));
        });
        $result = (new HealthChecker(new NullLogger(), $events, $timer))->deepCheck();
        self::assertSame('degraded', $result->getStatus());
    }
}
