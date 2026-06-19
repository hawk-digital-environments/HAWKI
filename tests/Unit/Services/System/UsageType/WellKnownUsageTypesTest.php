<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\UsageType;

use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(WellKnownUsageTypes::class)]
class WellKnownUsageTypesTest extends TestCase
{
    // =========================================================================

    public function testItExposesMainAppConstant(): void
    {
        static::assertSame('main', WellKnownUsageTypes::MAIN_APP);
    }

    public function testItExposesExternalAppConstant(): void
    {
        static::assertSame('external', WellKnownUsageTypes::EXTERNAL_APP);
    }

}
