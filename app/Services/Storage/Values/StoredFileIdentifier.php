<?php
declare(strict_types=1);


namespace App\Services\Storage\Values;


use App\Models\Attachment;
use App\Models\Room;
use App\Models\User;
use App\Services\Storage\Exception\CouldNotInflectStoredFieldIdentifierException;
use App\Services\Storage\Exception\InvalidStorageFileIdentifierStringGivenException;
use Illuminate\Support\Str;

/**
 * Uniquely identifies a file in the storage system.
 *
 * String format: `{category}-{uuid}[.{extension}]`
 * Example: `private-550e8400-e29b-41d4-a716-446655440000.pdf`
 *
 * The extension is optional and only carried as metadata (it is NOT the actual disk extension,
 * which may be `.blob` for security reasons). It is used to derive the correct filename when
 * serving the file to a browser or passing it to a converter.
 *
 * Usage:
 * ```php
 * // Create from an uploaded file (generates a new UUID)
 * $identifier = StoredFileIdentifier::fromCategoryAndFilename(StoredFileCategory::PRIVATE, 'report.pdf');
 *
 * // Round-trip through a string (e.g. from a route parameter)
 * $identifier = StoredFileIdentifier::fromString('private-550e8400-e29b-41d4-a716-446655440000.pdf');
 *
 * // Derive from a domain model
 * $identifier = StoredFileIdentifier::tryFromUserAvatar($user); // null if no avatar set
 * ```
 */
readonly class StoredFileIdentifier implements \Stringable, \JsonSerializable
{
    private function __construct(
        /**
         * The category of the stored file, used to group files and determine storage paths.
         */
        public StoredFileCategory $category,
        /**
         * The unique identifier for the stored file, which tell the storage engine where to find the file.
         */
        public string             $uuid,
        private string|null       $extension,
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return implode('-', [$this->category->value, $this->uuid]) .
            ($this->extension === null ? '' : '.' . $this->extension);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): string
    {
        return (string)$this;
    }

    /**
     * Derives an identifier from an Attachment model.
     * Throws if the attachment is missing a uuid, category, or carries an unknown category value.
     */
    public static function fromAttachment(Attachment $attachment): self
    {
        if (empty($attachment->uuid)) {
            throw new CouldNotInflectStoredFieldIdentifierException("Attachment with id {$attachment->id} does not have a uuid set.");
        }
        if (empty($attachment->category)) {
            throw new CouldNotInflectStoredFieldIdentifierException("Attachment with id {$attachment->id} does not have a category set.");
        }
        $category = StoredFileCategory::tryFrom($attachment->category);
        if ($category === null) {
            throw new CouldNotInflectStoredFieldIdentifierException("Attachment with id {$attachment->id} has an invalid category '{$attachment->category}' that does not match any known StoredFileCategory.");
        }
        return new self(
            $category,
            $attachment->uuid,
            pathinfo($attachment->name, PATHINFO_EXTENSION)
        );
    }

    /**
     * Derives an identifier from a User's avatar. Throws if the user has no avatar set.
     * Use {@see tryFromUserAvatar} when the avatar may legitimately be absent.
     */
    public static function fromUserAvatar(User $user): self
    {
        $result = self::tryFromUserAvatar($user);
        if ($result === null) {
            throw new CouldNotInflectStoredFieldIdentifierException("User with id {$user->id} does not have an avatar id set.");
        }
        return $result;
    }

    /**
     * Returns null when the user has no avatar set, avoiding a thrown exception for the common "no avatar yet" case.
     */
    public static function tryFromUserAvatar(User|null $user): self|null
    {
        if (empty($user->avatar_id)) {
            return null;
        }

        return new self(
            StoredFileCategory::PROFILE_AVATAR,
            $user->avatar_id,
            null
        );
    }

    /**
     * Derives an identifier from a Room's avatar. Throws if the room has no icon set.
     * Use {@see tryFromRoomAvatar} when the icon may legitimately be absent.
     */
    public static function fromRoomAvatar(Room $room): self
    {
        $result = self::tryFromRoomAvatar($room);
        if ($result === null) {
            throw new CouldNotInflectStoredFieldIdentifierException("Room with id {$room->id} does not have a room icon set.");
        }
        return $result;
    }

    /**
     * Returns null when the room has no icon set, avoiding a thrown exception for the common "no icon yet" case.
     */
    public static function tryFromRoomAvatar(Room $room): self|null
    {
        if (empty($room->room_icon)) {
            return null;
        }

        return new self(
            StoredFileCategory::ROOM_AVATAR,
            $room->room_icon,
            null
        );
    }

    /**
     * Parses an identifier from its string representation (e.g. a route parameter or JSON value).
     * Throws {@see InvalidStorageFileIdentifierStringGivenException} when the string is malformed
     * or contains an unknown category prefix.
     */
    public static function fromString(string $id): self
    {
        $parts = explode('-', $id, 2);
        if (count($parts) !== 2) {
            throw new InvalidStorageFileIdentifierStringGivenException($id);
        }
        [$categoryString, $rest] = $parts;

        $category = StoredFileCategory::tryFrom($categoryString);
        if ($category === null) {
            throw new InvalidStorageFileIdentifierStringGivenException($id);
        }

        $parts = explode('.', $rest, 2);
        $uuid = $parts[0];
        $extension = $parts[1] ?? null;

        return new self(
            $category,
            $uuid,
            $extension
        );
    }

    public static function fromCategoryAndUuid(StoredFileCategory $category, string $uuid, ?string $extension = null): self
    {
        return new self($category, $uuid, $extension);
    }

    /**
     * Creates a new identifier for an incoming upload, generating a fresh UUID and extracting
     * the file extension from the original filename. Used by the storage engine on every store call.
     */
    public static function fromCategoryAndFilename(StoredFileCategory $category, string $filename): self
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        return new self($category, (string)Str::uuid(), $extension);
    }
}
