<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Time;

use App\Services\System\Time\Exceptions\InvalidTimezoneException;
use App\Services\System\Time\TimezoneGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(TimezoneGuard::class)]
class TimezoneGuardTest extends TestCase
{
    // =========================================================================
    // Safe timezones
    // =========================================================================

    public static function safeTimezones(): iterable
    {
        yield 'location zone' => ['Europe/Berlin'];
        yield 'another location zone' => ['America/New_York'];
        yield 'UTC literal' => ['UTC'];
        yield 'Etc fixed offset' => ['Etc/UTC'];
        yield 'Etc GMT offset' => ['Etc/GMT+5'];
    }

    #[DataProvider('safeTimezones')]
    public function testItAcceptsSafeTimezone(string $timezone): void
    {
        TimezoneGuard::ensureSafe($timezone);

        static::addToAssertionCount(1);
    }

    // =========================================================================
    // Rejected abbreviations and invalid zones
    // =========================================================================

    public static function unsafeTimezones(): iterable
    {
        yield 'CET abbreviation' => ['CET'];
        yield 'EST abbreviation' => ['EST'];
        yield 'PST abbreviation' => ['PST'];
        yield 'GMT abbreviation' => ['GMT'];
        yield 'typo' => ['Europe/Berli'];
        yield 'empty' => [''];
    }

    #[DataProvider('unsafeTimezones')]
    public function testItRejectsUnsafeTimezone(string $timezone): void
    {
        static::expectException(InvalidTimezoneException::class);
        static::expectExceptionMessage('is not a valid IANA location timezone');

        TimezoneGuard::ensureSafe($timezone);
    }

    public function testItIncludesTheOffendingTimezoneInTheMessage(): void
    {
        try {
            TimezoneGuard::ensureSafe('CET');
            static::fail('Expected InvalidTimezoneException to be thrown.');
        } catch (InvalidTimezoneException $e) {
            static::assertStringContainsString('"CET"', $e->getMessage());
        }
    }
}
