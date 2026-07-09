<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\Repositories\Attributes;

use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UseModel::class)]
class UseModelTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new UseModel('App\\Models\\User');

        static::assertInstanceOf(UseModel::class, $sut);
    }

    public function testItStoresClass(): void
    {
        $sut = new UseModel('App\\Models\\User');

        static::assertSame('App\\Models\\User', $sut->class);
    }

    // =========================================================================
    // PHP Attribute target
    // =========================================================================

    public function testItIsPhpAttribute(): void
    {
        $reflection = new \ReflectionClass(UseModel::class);
        $attrs = $reflection->getAttributes(\Attribute::class);

        static::assertNotEmpty($attrs);
    }

    public function testItTargetsClass(): void
    {
        $reflection = new \ReflectionClass(UseModel::class);
        $attrs = $reflection->getAttributes(\Attribute::class);
        $flags = $attrs[0]->newInstance()->flags;

        static::assertSame(\Attribute::TARGET_CLASS, $flags);
    }
}
