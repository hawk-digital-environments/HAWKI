<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\SystemModels;

use App\Services\Ai\SystemModels\SystemModelRegistry;
use App\Services\Ai\SystemModels\Values\WellKnownSystemModelTypes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemModelRegistry::class)]
class SystemModelRegistryTest extends TestCase
{
    public function testItConstructs(): void
    {
        static::assertInstanceOf(SystemModelRegistry::class, new SystemModelRegistry());
    }

    public function testItCollectsDeclaredEntriesInOrder(): void
    {
        $sut = (new SystemModelRegistry())
            ->declare('assistant_guide', WellKnownSystemModelTypes::ASSISTANT_GUIDE, 'gpt-5.6-luna');

        static::assertSame(
            ['assistant_guide' => ['type' => WellKnownSystemModelTypes::ASSISTANT_GUIDE, 'defaultModelId' => 'gpt-5.6-luna']],
            $sut->entries(),
        );
    }

    public function testRedeclaringAKeyOverridesIt(): void
    {
        $sut = (new SystemModelRegistry())
            ->declare('assistant_guide', WellKnownSystemModelTypes::ASSISTANT_GUIDE, 'old-model')
            ->declare('assistant_guide', WellKnownSystemModelTypes::ASSISTANT_GUIDE, 'new-model');

        static::assertSame('new-model', $sut->entries()['assistant_guide']['defaultModelId']);
        static::assertSame(['assistant_guide' => 'new-model'], $sut->systemModels());
    }

    public function testNullAndEmptyDefaultsContributeNoAssignment(): void
    {
        $sut = (new SystemModelRegistry())
            ->declare('unset', WellKnownSystemModelTypes::DEFAULT, null)
            ->declare('empty', WellKnownSystemModelTypes::DEFAULT, '');

        static::assertSame([], $sut->systemModels());
        // The declarations still exist — a changed default re-triggers sync.
        static::assertCount(2, $sut->entries());
    }
}
