<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

/**
 * The two lists an assistant's `assistant_shared_users` rows form: the
 * exclusive audience (use only, once the exclusive stage is approved) and
 * the co-authors (edit at any stage). The pivot's `can_edit` flag tells them apart.
 */
enum AssistantShareRole: string
{
    case AUDIENCE = 'audience';
    case CO_AUTHOR = 'co_author';

    public function canEdit(): bool
    {
        return self::CO_AUTHOR === $this;
    }
}
