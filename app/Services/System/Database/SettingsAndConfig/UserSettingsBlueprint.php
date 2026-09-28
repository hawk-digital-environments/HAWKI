<?php

declare(strict_types=1);

namespace App\Services\System\Database\SettingsAndConfig;

use App\Services\Users\Settings\AbstractUserSettings;

/**
 * Migration blueprint for structural changes to user-settings classes.
 *
 * Identical to {@see ConfigAndSettingsBlueprint}, but bound to the rows of one namespace of **one
 * user**: `UserSettingsSchema::update()` runs the migration closure once per user that
 * has rows for the namespace, constructing a fresh blueprint per user, so the
 * developer-facing closure code is identical to the config flavor. The user id is
 * exposed for context when a transform needs user-dependent logic.
 *
 * @template T of AbstractUserSettings
 *
 * @extends ConfigAndSettingsBlueprint<T>
 */
class UserSettingsBlueprint extends ConfigAndSettingsBlueprint
{
    /**
     * @param class-string<T>            $settingsClass the user-settings class being migrated
     * @param array<string, null|string> $rawRows       raw stored rows of this user, keyed by property name
     * @param int                        $userId        the id of the user whose rows the blueprint operates on
     */
    public function __construct(
        string $settingsClass,
        array $rawRows,
        private readonly int $userId,
    ) {
        parent::__construct($settingsClass, $rawRows);
    }

    /**
     * The id of the user whose rows this blueprint operates on.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }
}
