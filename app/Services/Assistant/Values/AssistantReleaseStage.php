<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

enum AssistantReleaseStage: string
{
    case DRAFT = 'draft';
    case PRIVATE = 'private';
    case EXCLUSIVE = 'exclusive';
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
     * Visibility ladder ordering: draft < private < exclusive < organizational < federated.
     *
     * A higher value means broader visibility. Used to classify release
     * transitions as upward (requires review approval) or downward (free).
     */
    public function order(): int
    {
        return match ($this) {
            self::DRAFT => 0,
            self::PRIVATE => 1,
            self::EXCLUSIVE => 2,
            self::ORGANIZATIONAL => 3,
            self::FEDERATED => 4,
        };
    }

    /**
     * Stages that reach people beyond the creator and co-authors, and
     * therefore need a review approval before an assistant may enter them.
     * Exclusive reaches only its audience, yet is reviewed all the same.
     */
    public function requiresReview(): bool
    {
        return self::EXCLUSIVE === $this || self::ORGANIZATIONAL === $this || self::FEDERATED === $this;
    }
}
