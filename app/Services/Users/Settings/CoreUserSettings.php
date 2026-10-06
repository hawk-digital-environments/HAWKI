<?php

declare(strict_types=1);

namespace App\Services\Users\Settings;

use App\Services\System\Http\Attributes\ValidateInput;
use App\Services\Users\Settings\Values\Theme;

/**
 * The core user settings of the HAWKI application.
 *
 * Resolves to the `'hawki-core'` namespace via the plugin groundwork; its
 * {@see publicKey()} is `'core'`, so the settings appear as the `core` key inside the
 * namespace-scoped `user-settings` JSON:API resource.
 *
 * @api
 *
 * @see UserSettingsService
 */
class CoreUserSettings extends AbstractUserSettings
{
    /**
     * The user's preferred UI locale, e.g. `"de_DE"` — or null when the user follows
     * the application default. Read via
     * {@see \App\Services\Translation\LocaleService}, which validates the stored value
     * against the active locales on every read (stale values fall back gracefully).
     */
    #[ValidateInput('sometimes|nullable|string|max:5')]
    public ?string $locale = null;

    /**
     * The user's UI theme preference. `auto` (the default) makes the frontend follow
     * the browser's `prefers-color-scheme`; `light` / `dark` pin the colour scheme.
     */
    #[ValidateInput('sometimes|in:auto,light,dark')]
    public Theme $theme = Theme::Auto;

    /**
     * The user's timezone as a timezone identifier, e.g. `"Europe/Berlin"`.
     */
    #[ValidateInput('sometimes|timezone')]
    public string $timezone = 'UTC';

    /**
     * {@inheritDoc}
     */
    public static function publicKey(): string
    {
        return 'core';
    }
}
