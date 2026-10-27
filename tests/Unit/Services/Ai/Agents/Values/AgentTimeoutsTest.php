<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Values;

use App\Services\Ai\Agents\Values\AgentTimeouts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(AgentTimeouts::class)]
class AgentTimeoutsTest extends TestCase
{
    public function testItReadsTheTimeoutsFromTheDriverConfig(): void
    {
        $sut = AgentTimeouts::fromDriverConfig(['request_timeout' => 300, 'stall_timeout' => 45]);

        static::assertSame(300, $sut->forRequest());
        static::assertSame(45, $sut->forStream());
    }

    public function testItAcceptsNumericStrings(): void
    {
        // Values from env variables arrive as strings
        $sut = AgentTimeouts::fromDriverConfig(['request_timeout' => '300', 'stall_timeout' => ' 45 ']);

        static::assertSame(300, $sut->forRequest());
        static::assertSame(45, $sut->forStream());
    }

    public static function provideInvalidValues(): iterable
    {
        yield 'missing' => [null];
        yield 'empty string' => [''];
        yield 'not numeric' => ['abc'];
        yield 'negative' => [-5];
        yield 'negative string' => ['-5'];
        yield 'float string' => ['1.5'];
        yield 'array' => [[]];
    }

    #[DataProvider('provideInvalidValues')]
    public function testItFallsBackToTheDefaultsForInvalidValues(mixed $value): void
    {
        $sut = AgentTimeouts::fromDriverConfig(['request_timeout' => $value, 'stall_timeout' => $value]);

        static::assertSame(AgentTimeouts::DEFAULT_REQUEST_TIMEOUT, $sut->forRequest());
        static::assertSame(AgentTimeouts::DEFAULT_STALL_TIMEOUT, $sut->forStream());
    }

    public function testZeroRemovesTheRequestLimit(): void
    {
        static::assertSame(0, AgentTimeouts::fromDriverConfig(['request_timeout' => 0])->forRequest());
    }

    public function testZeroDisablesStallDetectionWithoutFallingBackToTheSocketTimeout(): void
    {
        // A Guzzle timeout of 0 would make streams fall back to PHP's default_socket_timeout
        $sut = AgentTimeouts::fromDriverConfig(['stall_timeout' => 0]);

        static::assertSame(0, $sut->stallTimeout);
        static::assertSame(AgentTimeouts::DISABLED_STALL_READ_TIMEOUT, $sut->forStream());
    }
}
