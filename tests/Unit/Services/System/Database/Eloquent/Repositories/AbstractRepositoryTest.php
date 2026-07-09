<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\Repositories;

use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Exceptions\InvalidRepositoryModelClassException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Services\System\Database\Eloquent\Repositories\AbstractRepositoryTestFixtures\InvalidModelRepository;
use Tests\Unit\Services\System\Database\Eloquent\Repositories\AbstractRepositoryTestFixtures\TestEloquentModel;
use Tests\Unit\Services\System\Database\Eloquent\Repositories\AbstractRepositoryTestFixtures\TestRepository;

#[CoversClass(AbstractRepository::class)]
class AbstractRepositoryTest extends TestCase
{
    // =========================================================================
    // getModelClass
    // =========================================================================

    public function testItGetModelClassReturnsResolvedClass(): void
    {
        $sut = new TestRepository();

        static::assertSame(TestEloquentModel::class, $sut->getModelClass());
    }

    public function testItGetModelClassCachesResult(): void
    {
        $sut = new TestRepository();

        $first = $sut->getModelClass();
        $second = $sut->getModelClass();

        static::assertSame($first, $second);
    }

    // =========================================================================
    // getEloquentInstance (via getModelClass override)
    // =========================================================================

    public function testItThrowsWhenModelClassIsNotEloquentModel(): void
    {
        $sut = new InvalidModelRepository();

        $this->expectException(InvalidRepositoryModelClassException::class);
        $this->expectExceptionMessage(\stdClass::class);

        // findAll() calls getQuery() → getEloquentInstance() which validates the model class
        $sut->findAll();
    }
}
