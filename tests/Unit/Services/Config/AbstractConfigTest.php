<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Config;

use App\Services\Config\AbstractConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Services\Config\ConfigServiceTestFixtures\ConcreteConfig;

#[CoversClass(AbstractConfig::class)]
class AbstractConfigTest extends TestCase
{
    // =========================================================================
    // namespace
    // =========================================================================

    public function testItNamespaceReturnsHawkiCore(): void
    {
        static::assertSame('hawki-core', ConcreteConfig::namespace());
    }

    public function testItNamespaceIsInheritedBySubclasses(): void
    {
        $sut = ConcreteConfig::fromArray([]);

        static::assertSame('hawki-core', $sut::namespace());
    }
}
