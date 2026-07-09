<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Storage\Values;

use App\Models\Attachment;
use App\Models\Room;
use App\Models\User;
use App\Services\Storage\Exception\CouldNotInflectStoredFieldIdentifierException;
use App\Services\Storage\Exception\InvalidStorageFileIdentifierStringGivenException;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(StoredFileIdentifier::class)]
class StoredFileIdentifierTest extends TestCase
{
    private const UUID = '550e8400-e29b-41d4-a716-446655440000';

    // =========================================================================
    // fromCategoryAndUuid
    // =========================================================================

    public function testItFromCategoryAndUuidSetsProperties(): void
    {
        $sut = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, self::UUID, 'pdf');

        static::assertSame(StoredFileCategory::PRIVATE, $sut->category);
        static::assertSame(self::UUID, $sut->uuid);
    }

    public function testItFromCategoryAndUuidToStringIncludesExtension(): void
    {
        $sut = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, self::UUID, 'pdf');

        static::assertSame('private-' . self::UUID . '.pdf', (string) $sut);
    }

    public function testItFromCategoryAndUuidToStringWithoutExtension(): void
    {
        $sut = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PROFILE_AVATAR, self::UUID);

        static::assertSame('profile_avatars-' . self::UUID, (string) $sut);
    }

    // =========================================================================
    // fromCategoryAndFilename
    // =========================================================================

    public function testItFromCategoryAndFilenameExtractsExtension(): void
    {
        $sut = StoredFileIdentifier::fromCategoryAndFilename(StoredFileCategory::GROUP, 'report.pdf');

        static::assertSame(StoredFileCategory::GROUP, $sut->category);
        static::assertStringEndsWith('.pdf', (string) $sut);
        static::assertNotEmpty($sut->uuid);
    }

    public function testItFromCategoryAndFilenameGeneratesUniqueUuids(): void
    {
        $a = StoredFileIdentifier::fromCategoryAndFilename(StoredFileCategory::PRIVATE, 'file.txt');
        $b = StoredFileIdentifier::fromCategoryAndFilename(StoredFileCategory::PRIVATE, 'file.txt');

        static::assertNotSame($a->uuid, $b->uuid);
    }

    // =========================================================================
    // fromString
    // =========================================================================

    public function testItFromStringParsesFullIdentifier(): void
    {
        $sut = StoredFileIdentifier::fromString('private-' . self::UUID . '.pdf');

        static::assertSame(StoredFileCategory::PRIVATE, $sut->category);
        static::assertSame(self::UUID, $sut->uuid);
        static::assertSame('private-' . self::UUID . '.pdf', (string) $sut);
    }

    public function testItFromStringParsesIdentifierWithoutExtension(): void
    {
        $sut = StoredFileIdentifier::fromString('group-' . self::UUID);

        static::assertSame(StoredFileCategory::GROUP, $sut->category);
        static::assertSame(self::UUID, $sut->uuid);
        static::assertSame('group-' . self::UUID, (string) $sut);
    }

    public function testItFromStringThrowsOnMissingDashSeparator(): void
    {
        $this->expectException(InvalidStorageFileIdentifierStringGivenException::class);
        $this->expectExceptionMessage("The given string 'nodashhere' is not a valid storage file identifier.");

        StoredFileIdentifier::fromString('nodashhere');
    }

    public function testItFromStringThrowsOnUnknownCategory(): void
    {
        $this->expectException(InvalidStorageFileIdentifierStringGivenException::class);

        StoredFileIdentifier::fromString('unknown_category-' . self::UUID);
    }

    public function testItFromStringThrowsOnEmptyString(): void
    {
        $this->expectException(InvalidStorageFileIdentifierStringGivenException::class);

        StoredFileIdentifier::fromString('');
    }

    // =========================================================================
    // tryFromUserAvatar / fromUserAvatar
    // =========================================================================

    public function testItTryFromUserAvatarReturnsNullWhenAvatarIdIsEmpty(): void
    {
        $user = new User();

        static::assertNull(StoredFileIdentifier::tryFromUserAvatar($user));
    }

    public function testItTryFromUserAvatarReturnsNullForNullUser(): void
    {
        static::assertNull(StoredFileIdentifier::tryFromUserAvatar(null));
    }

    public function testItTryFromUserAvatarReturnsIdentifierWhenAvatarIdIsSet(): void
    {
        $user = new User();
        $user->avatar_id = self::UUID;

        $sut = StoredFileIdentifier::tryFromUserAvatar($user);

        static::assertNotNull($sut);
        static::assertSame(StoredFileCategory::PROFILE_AVATAR, $sut->category);
        static::assertSame(self::UUID, $sut->uuid);
    }

    public function testItFromUserAvatarThrowsWhenAvatarIdIsEmpty(): void
    {
        $user = new User();
        $user->id = 99;

        $this->expectException(CouldNotInflectStoredFieldIdentifierException::class);
        $this->expectExceptionMessage('Could not inflect stored field identifier: User with id 99 does not have an avatar id set.');

        StoredFileIdentifier::fromUserAvatar($user);
    }

    public function testItFromUserAvatarReturnsIdentifierWhenAvatarIdIsSet(): void
    {
        $user = new User();
        $user->avatar_id = self::UUID;

        $sut = StoredFileIdentifier::fromUserAvatar($user);

        static::assertSame(self::UUID, $sut->uuid);
        static::assertSame(StoredFileCategory::PROFILE_AVATAR, $sut->category);
    }

    // =========================================================================
    // tryFromRoomAvatar / fromRoomAvatar
    // =========================================================================

    public function testItTryFromRoomAvatarReturnsNullWhenRoomIconIsEmpty(): void
    {
        $room = new Room();

        static::assertNull(StoredFileIdentifier::tryFromRoomAvatar($room));
    }

    public function testItTryFromRoomAvatarReturnsIdentifierWhenRoomIconIsSet(): void
    {
        $room = new Room();
        $room->room_icon = self::UUID;

        $sut = StoredFileIdentifier::tryFromRoomAvatar($room);

        static::assertNotNull($sut);
        static::assertSame(StoredFileCategory::ROOM_AVATAR, $sut->category);
        static::assertSame(self::UUID, $sut->uuid);
    }

    public function testItFromRoomAvatarThrowsWhenRoomIconIsEmpty(): void
    {
        $room = new Room();
        $room->id = 7;

        $this->expectException(CouldNotInflectStoredFieldIdentifierException::class);
        $this->expectExceptionMessage('Could not inflect stored field identifier: Room with id 7 does not have a room icon set.');

        StoredFileIdentifier::fromRoomAvatar($room);
    }

    public function testItFromRoomAvatarReturnsIdentifierWhenRoomIconIsSet(): void
    {
        $room = new Room();
        $room->room_icon = self::UUID;

        $sut = StoredFileIdentifier::fromRoomAvatar($room);

        static::assertSame(self::UUID, $sut->uuid);
        static::assertSame(StoredFileCategory::ROOM_AVATAR, $sut->category);
    }

    // =========================================================================
    // fromAttachment
    // =========================================================================

    public function testItFromAttachmentBuildsIdentifier(): void
    {
        $attachment = new Attachment();
        $attachment->uuid = self::UUID;
        $attachment->category = StoredFileCategory::GROUP->value;
        $attachment->name = 'document.pdf';

        $sut = StoredFileIdentifier::fromAttachment($attachment);

        static::assertSame(StoredFileCategory::GROUP, $sut->category);
        static::assertSame(self::UUID, $sut->uuid);
        static::assertSame('group-' . self::UUID . '.pdf', (string) $sut);
    }

    public function testItFromAttachmentThrowsWhenUuidIsEmpty(): void
    {
        $attachment = new Attachment();
        $attachment->id = 5;
        $attachment->category = StoredFileCategory::GROUP->value;

        $this->expectException(CouldNotInflectStoredFieldIdentifierException::class);
        $this->expectExceptionMessage('Attachment with id 5 does not have a uuid set.');

        StoredFileIdentifier::fromAttachment($attachment);
    }

    public function testItFromAttachmentThrowsWhenCategoryIsEmpty(): void
    {
        $attachment = new Attachment();
        $attachment->id = 5;
        $attachment->uuid = self::UUID;

        $this->expectException(CouldNotInflectStoredFieldIdentifierException::class);
        $this->expectExceptionMessage('Attachment with id 5 does not have a category set.');

        StoredFileIdentifier::fromAttachment($attachment);
    }

    public function testItFromAttachmentThrowsOnUnknownCategory(): void
    {
        $attachment = new Attachment();
        $attachment->id = 5;
        $attachment->uuid = self::UUID;
        $attachment->category = 'totally_unknown';

        $this->expectException(CouldNotInflectStoredFieldIdentifierException::class);
        $this->expectExceptionMessage("has an invalid category 'totally_unknown'");

        StoredFileIdentifier::fromAttachment($attachment);
    }

    // =========================================================================
    // __toString / jsonSerialize
    // =========================================================================

    public function testItJsonSerializesToSameStringAsToString(): void
    {
        $sut = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, self::UUID, 'docx');

        static::assertSame((string) $sut, $sut->jsonSerialize());
    }

    // =========================================================================
    // String format — all categories
    // =========================================================================

    public static function provideTestItFromStringParsesAllCategoriesData(): iterable
    {
        yield 'private' => [StoredFileCategory::PRIVATE, 'private'];
        yield 'group' => [StoredFileCategory::GROUP, 'group'];
        yield 'profile_avatars' => [StoredFileCategory::PROFILE_AVATAR, 'profile_avatars'];
        yield 'room_avatars' => [StoredFileCategory::ROOM_AVATAR, 'room_avatars'];
    }

    #[DataProvider('provideTestItFromStringParsesAllCategoriesData')]
    public function testItFromStringParsesAllCategories(StoredFileCategory $expected, string $prefix): void
    {
        $sut = StoredFileIdentifier::fromString($prefix . '-' . self::UUID);

        static::assertSame($expected, $sut->category);
    }
}
