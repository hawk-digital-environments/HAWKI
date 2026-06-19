<?php
declare(strict_types=1);


namespace App\Services\Storage\Values;


use App\Models\Attachment;
use App\Models\Room;
use App\Models\User;
use App\Services\Storage\Exception\CouldNotInflectStoredFieldIdentifierException;
use App\Services\Storage\Exception\InvalidStorageFileIdentifierStringGivenException;
use Illuminate\Support\Str;

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

    public static function fromUserAvatar(User $user): self
    {
        $result = self::tryFromUserAvatar($user);
        if ($result === null) {
            throw new CouldNotInflectStoredFieldIdentifierException("User with id {$user->id} does not have an avatar id set.");
        }
        return $result;
    }

    public static function tryFromUserAvatar(User|null $user): self|null
    {
        if (empty($user?->avatar_id)) {
            return null;
        }

        return new self(
            StoredFileCategory::PROFILE_AVATAR,
            $user->avatar_id,
            null
        );
    }

    public static function fromRoomAvatar(Room $room): self
    {
        $result = self::tryFromRoomAvatar($room);
        if ($result === null) {
            throw new CouldNotInflectStoredFieldIdentifierException("Room with id {$room->id} does not have a room icon set.");
        }
        return $result;
    }

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

    public static function fromCategoryAndFilename(StoredFileCategory $category, string $filename): self
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        return new self($category, (string)Str::uuid(), $extension);
    }
}
