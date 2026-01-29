<?php
declare(strict_types=1);


namespace App\Services\Translation\Loader;


use App\Services\Translation\Value\Locale;

interface TranslationLoaderInterface
{
    /**
     * Load translations for the given locale.
     * MUST not return null, but an empty array if no translations are found.
     * MUST not cache results, as this is handled by the caller.
     *
     * The expected format is a nested array compatible with Laravel's translation files:
     * [
     *   'group' => [
     *       'key' => 'translation',
     *       ...
     *   ],
     *   'key_without_group' => 'translation',
     *   ...
     * ]
     *
     * @param Locale $locale
     * @return array
     */
    public function load(Locale $locale): array;
}
