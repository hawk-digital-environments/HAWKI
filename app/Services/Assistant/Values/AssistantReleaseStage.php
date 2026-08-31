<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

enum AssistantReleaseStage: string
{
    case DRAFT = 'draft';
    case PRIVATE = 'private';
    case ORGANIZATIONAL = 'organizational';
    case FEDERATED = 'federated';

    /**
     * Stages that are broadly visible to every authenticated user.
     *
     * @return list<string>
     */
    public static function publiclyVisibleValues(): array
    {
        return [
            self::ORGANIZATIONAL->value,
            self::FEDERATED->value,
        ];
    }

    /**
     * Enum-case form of {@see publiclyVisibleValues()} for direct comparison
     * against a cast attribute (release_stage is now cast to this enum).
     *
     * @return list<self>
     */
    public static function publiclyVisibleCases(): array
    {
        return [self::ORGANIZATIONAL, self::FEDERATED];
    }

    /**
     * Visibility ladder ordering: draft < private < organizational < federated.
     *
     * A higher value means broader visibility. Used to classify release
     * transitions as upward (requires review approval) or downward (free).
     */
    public function order(): int
    {
        return match ($this) {
            self::DRAFT => 0,
            self::PRIVATE => 1,
            self::ORGANIZATIONAL => 2,
            self::FEDERATED => 3,
        };
    }

    /**
     * Stages whose visibility is broad enough to require a review approval
     * before an assistant may actually enter them.
     */
    public function isPublic(): bool
    {
        return self::ORGANIZATIONAL === $this || self::FEDERATED === $this;
    }
}
