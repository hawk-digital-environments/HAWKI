<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Health\Value;

use App\Services\System\Health\Value\HealthCheckResult;
use App\Services\System\Health\Value\HealthCheckResultCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(HealthCheckResultCollection::class)]
class HealthCheckResultCollectionTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new HealthCheckResultCollection();

        static::assertInstanceOf(HealthCheckResultCollection::class, $sut);
    }

    public function testItConstructsWithResults(): void
    {
        $result = new HealthCheckResult('db', HealthCheckResult::STATUS_OK, 'ok');
        $sut = new HealthCheckResultCollection($result);

        static::assertCount(1, $sut->results);
    }

    // =========================================================================
    // isOk
    // =========================================================================

    public function testItIsOkWhenEmpty(): void
    {
        $sut = new HealthCheckResultCollection();

        static::assertTrue($sut->isOk());
    }

    public function testItIsOkWhenAllResultsAreOk(): void
    {
        $sut = new HealthCheckResultCollection(
            new HealthCheckResult('db', HealthCheckResult::STATUS_OK, 'ok'),
            new HealthCheckResult('cache', HealthCheckResult::STATUS_OK, 'ok'),
        );

        static::assertTrue($sut->isOk());
    }

    public function testItIsNotOkWhenAnyResultIsError(): void
    {
        $sut = new HealthCheckResultCollection(
            new HealthCheckResult('db', HealthCheckResult::STATUS_OK, 'ok'),
            new HealthCheckResult('cache', HealthCheckResult::STATUS_ERROR, 'failed'),
        );

        static::assertFalse($sut->isOk());
    }

    public function testItIsNotOkWhenAllResultsAreError(): void
    {
        $sut = new HealthCheckResultCollection(
            new HealthCheckResult('db', HealthCheckResult::STATUS_ERROR, 'failed'),
            new HealthCheckResult('cache', HealthCheckResult::STATUS_ERROR, 'failed'),
        );

        static::assertFalse($sut->isOk());
    }

    // =========================================================================
    // getIterator
    // =========================================================================

    public function testItIsIterable(): void
    {
        $db = new HealthCheckResult('db', HealthCheckResult::STATUS_OK, 'ok');
        $cache = new HealthCheckResult('cache', HealthCheckResult::STATUS_ERROR, 'failed');
        $sut = new HealthCheckResultCollection($db, $cache);

        $collected = iterator_to_array($sut);

        static::assertSame($db, $collected['db']);
        static::assertSame($cache, $collected['cache']);
    }

    public function testItYieldsResultsKeyedByCheckName(): void
    {
        $result = new HealthCheckResult('my_check', HealthCheckResult::STATUS_OK, 'ok');
        $sut = new HealthCheckResultCollection($result);

        foreach ($sut as $name => $item) {
            static::assertSame('my_check', $name);
            static::assertSame($result, $item);
        }
    }

    // =========================================================================
    // jsonSerialize
    // =========================================================================

    public function testItJsonSerializesAllOkResults(): void
    {
        $sut = new HealthCheckResultCollection(
            new HealthCheckResult('db', HealthCheckResult::STATUS_OK, 'Database ok', 1.5),
        );

        $data = $sut->jsonSerialize();

        static::assertSame(HealthCheckResult::STATUS_OK, $data['status']);
        static::assertSame('All checks passed.', $data['message']);
        static::assertArrayHasKey('db', $data['results']);
        static::assertSame(HealthCheckResult::STATUS_OK, $data['results']['db']['status']);
        static::assertSame(1.5, $data['results']['db']['response_time']);
    }

    public function testItJsonSerializesWithErrorStatus(): void
    {
        $sut = new HealthCheckResultCollection(
            new HealthCheckResult('cache', HealthCheckResult::STATUS_ERROR, 'Cache failed'),
        );

        $data = $sut->jsonSerialize();

        static::assertSame(HealthCheckResult::STATUS_ERROR, $data['status']);
        static::assertSame('One or more checks failed.', $data['message']);
    }
}
