<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\Repositories\Exceptions;

use App\Services\System\Database\Eloquent\Repositories\Exceptions\CannotGuessRepositoryModelException;
use App\Services\System\Database\Eloquent\Repositories\Exceptions\RepositoryExceptionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CannotGuessRepositoryModelException::class)]
class CannotGuessRepositoryModelExceptionTest extends TestCase
{
    public function testItIsLogicException(): void
    {
        $sut = CannotGuessRepositoryModelException::forRepository('App\Repo', 'App\Attr\UseModel');

        static::assertInstanceOf(\LogicException::class, $sut);
    }

    public function testItImplementsRepositoryExceptionInterface(): void
    {
        $sut = CannotGuessRepositoryModelException::forRepository('App\Repo', 'App\Attr\UseModel');

        static::assertInstanceOf(RepositoryExceptionInterface::class, $sut);
    }

    // =========================================================================
    // forRepository
    // =========================================================================

    public function testItForRepositoryContainsRepositoryClass(): void
    {
        $sut = CannotGuessRepositoryModelException::forRepository('App\MyRepo', 'App\Attr\UseModel');

        static::assertStringContainsString('App\MyRepo', $sut->getMessage());
    }

    public function testItForRepositoryContainsAttributeClass(): void
    {
        $sut = CannotGuessRepositoryModelException::forRepository('App\MyRepo', 'App\Attr\UseModel');

        static::assertStringContainsString('App\Attr\UseModel', $sut->getMessage());
    }

    public function testItForRepositoryMatchesExpectedMessage(): void
    {
        $sut = CannotGuessRepositoryModelException::forRepository('App\MyRepo', 'App\Attr\UseModel');

        static::assertSame(
            'Could not guess model class for repository "App\MyRepo". Please specify the model class using the "App\Attr\UseModel" attribute.',
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forWrongUseModelAttribute
    // =========================================================================

    public function testItForWrongUseModelAttributeMatchesExpectedMessage(): void
    {
        $sut = CannotGuessRepositoryModelException::forWrongUseModelAttribute(
            'App\MyRepo',
            'Illuminate\UseModel',
            'App\Attr\UseModel',
        );

        static::assertSame(
            'The class "App\MyRepo" has the "Illuminate\UseModel" attribute, but it is the wrong one. Did you import the correct "App\Attr\UseModel" attribute?',
            $sut->getMessage()
        );
    }
}
