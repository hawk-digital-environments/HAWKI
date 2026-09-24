<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

/**
 * An admin's judgment on an uploaded knowledge file, set from the Publishing
 * Center's assistant detail page. `null` on the model means "not yet reviewed".
 */
enum AssistantAttachmentReviewStatus: string
{
    case OK = 'ok';
    case CORRUPTED = 'corrupted';
    case INADEQUATE = 'inadequate';
}
