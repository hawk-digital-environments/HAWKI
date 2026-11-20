<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assistant\Values;

use App\Services\Assistant\Values\AssistantShareGrant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssistantShareGrant::class)]
class AssistantShareGrantTest extends TestCase
{
    public function testItConstructs(): void
    {
        $sut = new AssistantShareGrant(userId: 7, canEdit: true);

        self::assertSame(7, $sut->userId);
        self::assertTrue($sut->canEdit);
    }

    public function testItDefaultsCanEditToFalse(): void
    {
        $sut = new AssistantShareGrant(userId: 7);

        self::assertFalse($sut->canEdit);
    }

    public function testItMapsResourceIdentifiers(): void
    {
        $sut = AssistantShareGrant::fromArray([
            'type' => 'users',
            'id' => '12',
            'meta' => ['can_edit' => true],
        ]);

        self::assertSame(12, $sut->userId);
        self::assertTrue($sut->canEdit);
    }

    public function testItMapsIdentifiersWithoutMeta(): void
    {
        $sut = AssistantShareGrant::fromArray(['type' => 'users', 'id' => 12]);

        self::assertSame(12, $sut->userId);
        self::assertFalse($sut->canEdit);
    }
}
