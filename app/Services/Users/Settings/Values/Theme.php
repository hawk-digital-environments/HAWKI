<?php

declare(strict_types=1);

namespace App\Services\Users\Settings\Values;

/**
 * The UI theme of a user — the backed values (`'light'` / `'dark'`) are exactly the
 * wire and storage format, matching the frontend's values.
 */
enum Theme: string
{
    case Light = 'light';
    case Dark = 'dark';
}
