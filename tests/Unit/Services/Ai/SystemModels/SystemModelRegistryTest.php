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
            ->declare('my_slice_model', 'my_slice_model', 'gpt-5.6-luna');

        static::assertSame(
            ['my_slice_model' => ['type' => 'my_slice_model', 'defaultModelId' => 'gpt-5.6-luna']],
            $sut->entries(),
        );
    }

    public function testRedeclaringAKeyOverridesIt(): void
    {
        $sut = (new SystemModelRegistry())
            ->declare('my_slice_model', 'my_slice_model', 'old-model')
            ->declare('my_slice_model', 'my_slice_model', 'new-model');

        static::assertSame('new-model', $sut->entries()['my_slice_model']['defaultModelId']);
        static::assertSame(['my_slice_model' => 'new-model'], $sut->systemModels());
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

    public function testItResolvesTypesForDeclaredKeysOnly(): void
    {
        $sut = (new SystemModelRegistry())
            ->declare('my_slice_model', 'my_slice_model', 'gpt-5.6-luna')
            ->declare('unset', WellKnownSystemModelTypes::DEFAULT, null);

        // Recognition derives from the declarations — including entries
        // without a default — so consumers never enumerate keys themselves.
        static::assertSame('my_slice_model', $sut->typeForKey('my_slice_model'));
        static::assertSame(WellKnownSystemModelTypes::DEFAULT, $sut->typeForKey('unset'));
        static::assertNull($sut->typeForKey('undeclared'));
    }
}
