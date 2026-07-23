<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\Repositories\Exceptions;

use App\Services\System\Database\Eloquent\Repositories\Exceptions\InvalidRepositoryModelClassException;
use App\Services\System\Database\Eloquent\Repositories\Exceptions\RepositoryExceptionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(InvalidRepositoryModelClassException::class)]
class InvalidRepositoryModelClassExceptionTest extends TestCase
{
    // =========================================================================

    public function testItIsLogicException(): void
    {
        $sut = InvalidRepositoryModelClassException::forNonEloquentClass(\stdClass::class);

        static::assertInstanceOf(\LogicException::class, $sut);
    }

    public function testItImplementsRepositoryExceptionInterface(): void
    {
        $sut = InvalidRepositoryModelClassException::forNonEloquentClass(\stdClass::class);

        static::assertInstanceOf(RepositoryExceptionInterface::class, $sut);
    }

    public function testItForNonEloquentClassContainsModelClassName(): void
    {
        $sut = InvalidRepositoryModelClassException::forNonEloquentClass(\stdClass::class);

        static::assertStringContainsString(\stdClass::class, $sut->getMessage());
    }

    public function testItForNonEloquentClassContainsEloquentModelReference(): void
    {
        $sut = InvalidRepositoryModelClassException::forNonEloquentClass(\stdClass::class);

        static::assertStringContainsString(\Illuminate\Database\Eloquent\Model::class, $sut->getMessage());
    }

    public function testItForNonEloquentClassMatchesExpectedMessage(): void
    {
        $sut = InvalidRepositoryModelClassException::forNonEloquentClass(\stdClass::class);

        static::assertSame(
            sprintf('Model class "%s" must be an instance of %s.', \stdClass::class, \Illuminate\Database\Eloquent\Model::class),
            $sut->getMessage()
        );
    }
}
