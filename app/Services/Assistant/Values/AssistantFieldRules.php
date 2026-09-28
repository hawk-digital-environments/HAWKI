<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

/**
 * The assistant fields' shared constraints, anchored to what actually holds
 * them: the `assistants` columns (`name`/`handle` VARCHAR(255), the prompt
 * and description TEXT columns, `assistant_avatars.name` VARCHAR(255) with
 * the save path's emoji cap) and the handle's identity format. One source of
 * truth for the JSON:API resource requests, the builder draft's validation,
 * and the handle-namespace probe — the limits must never drift apart, and
 * nothing here invents a cap the storage does not enforce.
 */
final class AssistantFieldRules
{
    /**
     * Mirrors the `assistants.name` column (VARCHAR(255)).
     */
    public const int NAME_MAX_LENGTH = 255;

    /**
     * Mirrors the `assistants.handle` column (VARCHAR(255)).
     */
    public const int HANDLE_MAX_LENGTH = 255;

    /**
     * Mirrors handle detection in AiHandleStore in frontend.
     */
    public const string HANDLE_PATTERN = '/^[a-zA-Z0-9_-]+$/';

    /**
     * The emoji an avatar proposal may carry. Matches the save path's cap on
     * `assistant_avatars.name` (see AssistantAvatarRequest): one grapheme
     * with a little room for combining sequences.
     */
    public const int AVATAR_EMOJI_MAX_LENGTH = 5;

    /**
     * @return list<string>
     */
    public static function name(): array
    {
        return ['string', 'max:' . self::NAME_MAX_LENGTH];
    }

    /**
     * The handle's format and length, without the uniqueness checks — those
     * belong to the write path alone (a draft may propose a taken handle; the
     * guide and the handles probe exist to resolve it).
     *
     * @return list<string>
     */
    public static function handle(): array
    {
        return ['string', 'max:' . self::HANDLE_MAX_LENGTH, 'regex:' . self::HANDLE_PATTERN];
    }

    /**
     * Free-text fields stored in TEXT columns: no invented length cap.
     *
     * @return list<string>
     */
    public static function text(): array
    {
        return ['string'];
    }

    /**
     * @return list<string>
     */
    public static function avatarEmoji(): array
    {
        return ['string', 'max:' . self::AVATAR_EMOJI_MAX_LENGTH];
    }
}
