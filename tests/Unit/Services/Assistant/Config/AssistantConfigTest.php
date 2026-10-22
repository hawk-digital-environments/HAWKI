<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assistant\Config;

use App\Services\Assistant\Config\AssistantConfig;
use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssistantConfig::class)]
class AssistantConfigTest extends TestCase
{
    public function testItReadsTheFileBackedValues(): void
    {
        $repo = new Repository([
            'assistant' => [
                'assistant_versions' => ['debounce_seconds' => 30],
                'builder' => ['guide_model' => 'some-model'],
            ],
        ]);

        $sut = AssistantConfig::make($repo);

        static::assertSame(30, $sut->assistantVersionsDebounceSeconds);
        static::assertSame('some-model', $sut->builderGuideModel);
    }

    public function testItFallsBackToTheDefaults(): void
    {
        $sut = AssistantConfig::make(new Repository([]));

        static::assertSame(10, $sut->assistantVersionsDebounceSeconds);
        static::assertSame('gpt-5.6-luna', $sut->builderGuideModel);
    }
}
