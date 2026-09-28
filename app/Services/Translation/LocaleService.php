<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Services\Translation\Config\LocaleConfig;
use App\Services\Translation\Exception\SettingUnavailableLocaleException;
use App\Services\Translation\Value\Locale;
use App\Services\Users\Settings\CoreUserSettings;
use App\Services\Users\Settings\UserSettingsService;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Foundation\Application;

#[Singleton()]
class LocaleService
{
    private ?Locale $currentLocale = null;

    public function __construct(
        private readonly Application $application,
        private readonly LocaleConfig $localeConfig,
        private readonly UserSettingsService $userSettingsService,
    ) {
    }

    /**
     * Given a locale id, returns the corresponding Locale instance, or null if not found.
     */
    public function getLocale(string|\Stringable $id): ?Locale
    {
        return $this->resolveLocaleObject($id);
    }

    /**
     * Returns the list of available and active locales.
     *
     * @return list<Locale>
     */
    public function getAvailableLocales(): array
    {
        return $this->localeConfig->available;
    }

    /**
     * Returns the instance of the currently resolved locale.
     */
    public function getCurrentLocale(): Locale
    {
        if (null === $this->currentLocale) {
            $this->resolveCurrentLocale();
        }

        return $this->currentLocale;
    }

    /**
     * Sets the current locale to the given instance.
     *
     * @param null|bool $persist If true, the locale is written to the user's core settings (per-user, or per session for guests). False or null will not persist it (default).
     *
     * @throws SettingUnavailableLocaleException if the given locale is not in the list of active locales
     */
    public function setCurrentLocale(Locale|string|\Stringable $locale, ?bool $persist = null): void
    {
        $validatedLocale = $this->resolveLocaleObject($locale);

        if (null === $validatedLocale) {
            throw new SettingUnavailableLocaleException(
                $locale,
                array_map(static fn (Locale $locale) => $locale->lang, $this->getAvailableLocales()),
            );
        }

        if ($persist) {
            $settings = $this->userSettingsService->get(CoreUserSettings::class);
            $settings->locale = $validatedLocale->lang;
            $this->userSettingsService->save($settings);
        }

        $this->currentLocale = $validatedLocale;
        $this->application->setLocale($validatedLocale->lang);
    }

    /**
     * Returns the instance of the default locale.
     */
    public function getDefaultLocale(): Locale
    {
        return $this->localeConfig->default;
    }

    /**
     * Given a locale (either as a string or as a Locale instance), returns the most likely locale to be used.
     * If the given locale is null, the current locale is returned.
     * If the given locale is the app locale, it is treated as if no locale was given.
     * If the given locale is not in the list of active locales, it is treated as if no locale was given.
     *
     * WHY: Because of historic reasons, the app locale is not consistently a valid locale for content,
     * so we must ALWAYS use our own current locale to determine the most likely locale to be used.
     * This is not ideal, but changing this would be a breaking change; therefore this method is a guessing game
     * to determine the most likely locale to be used.
     */
    public function getMostLikelyLocale(null|Locale|string $locale = null): Locale
    {
        // Not 100% sure about this to be honest...
        // If a Locale is given, we use its id; this means even if the appLocale is given as a Locale,
        // we do treat it as if no locale was given. This is to be consistent with the handling of strings below.
        if ($locale instanceof Locale) {
            $locale = $locale->lang;
        }

        // Because of historic reasons, we do not use the app locale, so if the given locale IS the app locale,
        // we treat it as if no locale was given.
        if ($this->application->getLocale() === $locale) {
            $locale = null;
        }

        return $this->resolveLocaleObject($locale) ?? $this->getCurrentLocale();
    }

    /**
     * Resolves the current locale from the user's core settings (per-user for
     * authenticated users, per-session for guests) or the application default —
     * in that order. The resolved locale is stored in $this->currentLocale.
     */
    private function resolveCurrentLocale(): void
    {
        $this->currentLocale = $this->getUserPreferredLocale() ?? $this->getDefaultLocale();
    }

    private function getUserPreferredLocale(): ?Locale
    {
        // Reads the user's core settings: the per-user locale for authenticated users,
        // the session-stored value for guests. Null follows the application default.
        $preferredLocale = $this->userSettingsService->get(CoreUserSettings::class)->locale;

        return null === $preferredLocale
            ? null
            : $this->resolveLocaleObject($preferredLocale);
    }

    /**
     * Given a locale as a string, returns the corresponding Locale instance, or null if not found.
     */
    private function resolveLocaleObject(null|string|\Stringable $locale): ?Locale
    {
        if (null === $locale) {
            return null;
        }

        $locale = mb_strtolower((string) $locale);

        if (mb_strlen($locale) === 5 && str_contains($locale, '-')) {
            // We expect the locale to be an underscore, but the input had a dash, so we replace it here
            $locale = str_replace('-', '_', $locale);
        }

        if (mb_strlen($locale) === 2) {
            // If the locale is given as a 2-letter code, we try to find a matching locale by comparing the first 2 letters of the active locales.
            foreach ($this->localeConfig->available as $configuredLocale) {
                if (str_starts_with(mb_strtolower($configuredLocale->lang), mb_strtolower($locale))) {
                    return $configuredLocale;
                }
            }
        }

        // We compare the given locale with the active locales in a case-insensitive way, and return the first match we find.
        foreach ($this->localeConfig->available as $configuredLocale) {
            if (mb_strtolower($configuredLocale->lang) === $locale) {
                return $configuredLocale;
            }
        }

        // If not found, try to match only the first two letters of the locale (e.g. "en" for "en_US")
        $localePrefix = mb_substr($locale, 0, 2);

        foreach ($this->localeConfig->available as $configuredLocale) {
            if (str_starts_with(mb_strtolower($configuredLocale->lang), mb_strtolower($localePrefix))) {
                return $configuredLocale;
            }
        }

        return null;
    }
}
