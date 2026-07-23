<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\Repositories\Traits;

use App\Services\System\Database\Eloquent\Repositories\Traits\GuessesModelNameTrait;
use App\Services\System\Database\Eloquent\Repositories\Exceptions\CannotGuessRepositoryModelException;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;
use Tests\Unit\Services\System\Database\Eloquent\Repositories\Traits\GuessesModelNameTraitTestFixtures\TestEloquentModel;
use Tests\Unit\Services\System\Database\Eloquent\Repositories\Traits\GuessesModelNameTraitTestFixtures\WithDocBlockAnnotationRepository;
use Tests\Unit\Services\System\Database\Eloquent\Repositories\Traits\GuessesModelNameTraitTestFixtures\WithNoHintsRepository;
use Tests\Unit\Services\System\Database\Eloquent\Repositories\Traits\GuessesModelNameTraitTestFixtures\WithUseModelAttributeRepository;
use Tests\Unit\Services\System\Database\Eloquent\Repositories\Traits\GuessesModelNameTraitTestFixtures\WithWrongAttributeRepository;

#[CoversTrait(GuessesModelNameTrait::class)]
class GuessesModelNameTraitTest extends TestCase
{
    // =========================================================================
    // Strategy 1: UseModel attribute
    // =========================================================================

    public function testItResolvesModelClassFromUseModelAttribute(): void
    {
        $repo = new WithUseModelAttributeRepository();

        static::assertSame(TestEloquentModel::class, $repo->getModelClass());
    }

    // =========================================================================
    // Strategy 2: @extends DocBlock annotation
    // =========================================================================

    public function testItResolvesModelClassFromDocBlockAnnotation(): void
    {
        $repo = new WithDocBlockAnnotationRepository();

        static::assertSame(TestEloquentModel::class, $repo->getModelClass());
    }

    // =========================================================================
    // Strategy 3: class name falls through (no match)
    // =========================================================================

    public function testItThrowsWhenNoStrategySucceeds(): void
    {
        $repo = new WithNoHintsRepository();

        $this->expectException(CannotGuessRepositoryModelException::class);

        $repo->getModelClass();
    }

    public function testItErrorMessageContainsRepositoryClass(): void
    {
        $repo = new WithNoHintsRepository();

        try {
            $repo->getModelClass();
            static::fail('Expected CannotGuessRepositoryModelException to be thrown.');
        } catch (CannotGuessRepositoryModelException $e) {
            static::assertStringContainsString(WithNoHintsRepository::class, $e->getMessage());
        }
    }

    // =========================================================================
    // Wrong UseModel attribute (Laravel's, not HAWKI's)
    // =========================================================================

    public function testItThrowsWhenLaravelUseModelAttributeIsUsedInstead(): void
    {
        $repo = new WithWrongAttributeRepository();

        $this->expectException(CannotGuessRepositoryModelException::class);

        $repo->getModelClass();
    }

    public function testItErrorMessageMentionsWrongAttributeForWrongUseModel(): void
    {
        $repo = new WithWrongAttributeRepository();

        try {
            $repo->getModelClass();
            static::fail('Expected CannotGuessRepositoryModelException to be thrown.');
        } catch (CannotGuessRepositoryModelException $e) {
            static::assertStringContainsString(WithWrongAttributeRepository::class, $e->getMessage());
        }
    }

    // =========================================================================
    // Caching: second call returns the same class without re-resolving
    // =========================================================================

    public function testItCachesModelClassAfterFirstResolution(): void
    {
        $repo = new WithUseModelAttributeRepository();

        $first = $repo->getModelClass();
        $second = $repo->getModelClass();

        static::assertSame($first, $second);
    }
}
