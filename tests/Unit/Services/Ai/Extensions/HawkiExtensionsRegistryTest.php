<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Extensions;

use App\Services\Ai\Extensions\HawkiExtensionsRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HawkiExtensionsRegistry::class)]
class HawkiExtensionsRegistryTest extends TestCase
{
    public function testItConstructs(): void
    {
        static::assertInstanceOf(HawkiExtensionsRegistry::class, new HawkiExtensionsRegistry());
    }

    public function testItBuildsPrefixedRulesForDeclaredLocationsOnly(): void
    {
        $sut = new HawkiExtensionsRegistry();
        $sut->declare('assistant_handle', [
            'payload.hawkiExtensions' => ['nullable', 'string', 'exists:assistants,handle'],
            'payload.messages.*.hawkiExtensions' => ['nullable', 'string', 'max:255'],
        ]);

        static::assertSame(
            ['payload.hawkiExtensions.assistant_handle' => ['nullable', 'string', 'exists:assistants,handle']],
            $sut->rulesFor('payload.hawkiExtensions'),
        );
        static::assertSame(
            ['payload.messages.*.hawkiExtensions.assistant_handle' => ['nullable', 'string', 'max:255']],
            $sut->rulesFor('payload.messages.*.hawkiExtensions'),
        );
    }

    public function testItReturnsNothingForUndeclaredPrefixes(): void
    {
        $sut = new HawkiExtensionsRegistry();
        $sut->declare('assistant_handle', [
            'payload.hawkiExtensions' => ['nullable', 'string'],
        ]);

        static::assertSame([], $sut->rulesFor('hawkiExtensions'));
    }

    public function testItFiltersMessagesToTheRequestedPrefixes(): void
    {
        $sut = new HawkiExtensionsRegistry();
        $sut->declare(
            'assistant_handle',
            [
                'payload.hawkiExtensions' => ['nullable', 'string'],
                'payload.messages.*.hawkiExtensions' => ['nullable', 'string'],
            ],
            [
                'payload.hawkiExtensions.assistant_handle' => 'No assistant found.',
                'payload.messages.*.hawkiExtensions.assistant_handle' => 'No assistant found here either.',
            ],
        );

        static::assertSame(
            ['payload.hawkiExtensions.assistant_handle' => 'No assistant found.'],
            $sut->messagesFor('payload.hawkiExtensions'),
        );
    }

    public function testRedeclaringAFieldMergesLocations(): void
    {
        $sut = new HawkiExtensionsRegistry();
        $sut->declare('assistant_handle', ['payload.hawkiExtensions' => ['nullable']]);
        $sut->declare('assistant_handle', ['payload.messages.*.hawkiExtensions' => ['nullable', 'string']]);

        static::assertSame(
            [
                'payload.hawkiExtensions.assistant_handle' => ['nullable'],
                'payload.messages.*.hawkiExtensions.assistant_handle' => ['nullable', 'string'],
            ],
            $sut->rulesFor('payload.hawkiExtensions', 'payload.messages.*.hawkiExtensions'),
        );
    }
}
