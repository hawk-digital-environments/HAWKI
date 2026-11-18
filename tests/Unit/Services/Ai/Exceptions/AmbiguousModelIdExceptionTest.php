<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Exceptions;

use App\Services\Ai\Exceptions\AmbiguousModelIdException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AmbiguousModelIdException::class)]
class AmbiguousModelIdExceptionTest extends TestCase
{
    // =========================================================================
    // forModelId
    // =========================================================================

    public function testItForModelIdIncludesAllCandidatesInTheMessage(): void
    {
        $sut = AmbiguousModelIdException::forModelId('gemma-4-31b-it', ['gwdg/gemma-4-31b-it', 'google/gemma-4-31b-it']);

        static::assertSame(
            'The model ID "gemma-4-31b-it" is offered by multiple providers, use one of: gwdg/gemma-4-31b-it, google/gemma-4-31b-it.',
            $sut->getMessage()
        );
    }

    // =========================================================================
    // Candidates
    // =========================================================================

    public function testItExposesTheCandidates(): void
    {
        $sut = AmbiguousModelIdException::forModelId('gemma-4-31b-it', ['gwdg/gemma-4-31b-it', 'google/gemma-4-31b-it']);

        static::assertSame(['gwdg/gemma-4-31b-it', 'google/gemma-4-31b-it'], $sut->getCandidates());
    }

    public function testItExposesTheFirstCandidateAsTheDeterministicChoice(): void
    {
        $sut = AmbiguousModelIdException::forModelId('gemma-4-31b-it', ['gwdg/gemma-4-31b-it', 'google/gemma-4-31b-it']);

        static::assertSame('gwdg/gemma-4-31b-it', $sut->getFirstCandidate());
    }

    public function testItReindexesTheCandidates(): void
    {
        $sut = AmbiguousModelIdException::forModelId('gemma-4-31b-it', ['gwdg/gemma-4-31b-it', 7 => 'google/gemma-4-31b-it']);

        static::assertSame([0 => 'gwdg/gemma-4-31b-it', 1 => 'google/gemma-4-31b-it'], $sut->getCandidates());
    }
}
