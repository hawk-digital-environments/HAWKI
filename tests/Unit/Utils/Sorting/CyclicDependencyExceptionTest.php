<?php
declare(strict_types=1);

namespace Tests\Unit\Utils\Sorting;

use App\Utils\Sorting\Exceptions\CyclicDependencyException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CyclicDependencyException::class)]
class CyclicDependencyExceptionTest extends TestCase
{
    // =========================================================================

    public function testItConstructsViaFactory(): void
    {
        $e = CyclicDependencyException::forLoopInPath(['a', 'b'], 'a');

        static::assertInstanceOf(CyclicDependencyException::class, $e);
    }

    // =========================================================================

    public function testItForLoopInPathBuildsMessageFromPathAndKey(): void
    {
        $e = CyclicDependencyException::forLoopInPath(['a', 'b'], 'a');

        static::assertSame('Found a cyclic dependency in: a -> b -> a', $e->getMessage());
    }

    public function testItForLoopInPathWorksWithSingleItemPath(): void
    {
        $e = CyclicDependencyException::forLoopInPath(['x'], 'x');

        static::assertSame('Found a cyclic dependency in: x -> x', $e->getMessage());
    }

    public function testItForLoopInPathWorksWithLongerChain(): void
    {
        $e = CyclicDependencyException::forLoopInPath(['a', 'b', 'c', 'd'], 'b');

        static::assertSame('Found a cyclic dependency in: a -> b -> c -> d -> b', $e->getMessage());
    }
}
