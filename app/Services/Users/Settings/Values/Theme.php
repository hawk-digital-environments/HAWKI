<?php

declare(strict_types=1);

namespace App\Services\Users\Settings\Values;

/**
 * The UI theme preference of a user — the backed values (`'auto'` / `'light'` /
 * `'dark'`) are exactly the wire and storage format, matching the frontend's values.
 *
 * `Auto` is the default: the frontend then follows the browser's
 * `prefers-color-scheme` live, and only an explicit `Light` / `Dark` choice pins
 * the colour scheme.
 */
enum Theme: string
{
    case Auto = 'auto';
    case Light = 'light';
    case Dark = 'dark';
}
