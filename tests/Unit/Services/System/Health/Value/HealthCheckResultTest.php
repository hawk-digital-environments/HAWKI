<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Health\Value;

use App\Services\System\Health\Value\HealthCheckResult;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(HealthCheckResult::class)]
class HealthCheckResultTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new HealthCheckResult('database', HealthCheckResult::STATUS_OK, 'ok');

        static::assertInstanceOf(HealthCheckResult::class, $sut);
    }

    public function testItConstructsWithResponseTime(): void
    {
        $sut = new HealthCheckResult('database', HealthCheckResult::STATUS_OK, 'ok', 3.14);

        static::assertSame(3.14, $sut->responseTime);
    }

    public function testItExposesCheckName(): void
    {
        $sut = new HealthCheckResult('my_check', HealthCheckResult::STATUS_OK, 'ok');

        static::assertSame('my_check', $sut->checkName);
    }

    public function testItExposesStatus(): void
    {
        $sut = new HealthCheckResult('my_check', HealthCheckResult::STATUS_ERROR, 'failed');

        static::assertSame(HealthCheckResult::STATUS_ERROR, $sut->status);
    }

    public function testItExposesMessage(): void
    {
        $sut = new HealthCheckResult('my_check', HealthCheckResult::STATUS_OK, 'All good');

        static::assertSame('All good', $sut->message);
    }

    public function testItHasNullResponseTimeByDefault(): void
    {
        $sut = new HealthCheckResult('my_check', HealthCheckResult::STATUS_OK, 'ok');

        static::assertNull($sut->responseTime);
    }

    // =========================================================================
    // Status constants
    // =========================================================================

    public function testItExposesStatusOkConstant(): void
    {
        static::assertSame('ok', HealthCheckResult::STATUS_OK);
    }

    public function testItExposesStatusErrorConstant(): void
    {
        static::assertSame('error', HealthCheckResult::STATUS_ERROR);
    }

    // =========================================================================
    // isOk / isError
    // =========================================================================

    public function testItIsOkReturnsTrueForOkStatus(): void
    {
        $sut = new HealthCheckResult('db', HealthCheckResult::STATUS_OK, 'ok');

        static::assertTrue($sut->isOk());
        static::assertFalse($sut->isError());
    }

    public function testItIsErrorReturnsTrueForErrorStatus(): void
    {
        $sut = new HealthCheckResult('db', HealthCheckResult::STATUS_ERROR, 'failed');

        static::assertTrue($sut->isError());
        static::assertFalse($sut->isOk());
    }

    // =========================================================================
    // jsonSerialize
    // =========================================================================

    public function testItJsonSerializesWithResponseTime(): void
    {
        $sut = new HealthCheckResult('database', HealthCheckResult::STATUS_OK, 'ok', 5.5);

        $data = $sut->jsonSerialize();

        static::assertSame('database', $data['name']);
        static::assertSame(HealthCheckResult::STATUS_OK, $data['status']);
        static::assertSame('ok', $data['message']);
        static::assertSame(5.5, $data['response_time']);
    }

    public function testItJsonSerializeOmitsResponseTimeWhenNull(): void
    {
        $sut = new HealthCheckResult('database', HealthCheckResult::STATUS_OK, 'ok');

        $data = $sut->jsonSerialize();

        static::assertArrayNotHasKey('response_time', $data);
    }
}
