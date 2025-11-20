<?php
declare(strict_types=1);


namespace App\Services\Translation;


use App\Services\Translation\Exception\DefaultLocaleIsNotActiveException;
use App\Services\Translation\Exception\SettingUnavailableLocaleException;
use App\Services\Translation\Value\Locale;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Session\Session;
use Illuminate\Cookie\CookieJar;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Stringable;

#[Singleton]
class LocaleService
{
    private const SESSION_LANGUAGE_KEY = 'language';
    private const LAST_LANGUAGE_COOKIE_KEY = 'lastLanguage_cookie';
    
    private Locale|null $currentLocale = null;
    
    /**
     * @var Locale[]
     */
    private array $locales;
    /**
     * @var string[]
     */
    private array $activeLocaleIds;
    
    public function __construct(
        private readonly Application $application,
        private readonly Session     $session,
        private readonly CookieJar   $cookieJar,
        #[Config('locale.default_language')]
        private readonly string      $defaultLocale,
        #[Config('locale.langs')]
        array                        $localeConfig
    )
    {
        $this->locales = Locale::createListByConfig($localeConfig);
        $this->activeLocaleIds = array_map(static fn(Locale $locale) => $locale->lang, $this->locales);
        if (!in_array($this->defaultLocale, $this->activeLocaleIds, true)) {
            throw new DefaultLocaleIsNotActiveException(
                defaultLocale: $this->defaultLocale,
                activeLocaleIds: $this->activeLocaleIds,
            );
        }
    }
    
    /**
     * Given a locale id, returns the corresponding Locale instance, or null if not found.
     * @param string|\Stringable $id
     * @return Locale|null
     */
    public function getLocale(string|\Stringable $id): Locale|null
    {
        return $this->resolveLocaleObject($id);
    }
    
    /**
     * Returns the list of available and active locales.
     * @return Locale[]
     */
    public function getAvailableLocales(): array
    {
        return $this->locales;
    }
    
    /**
     * Returns the instance of the currently resolved locale.
     * @return Locale
     */
    public function getCurrentLocale(): Locale
    {
        if ($this->currentLocale === null) {
            $this->resolveCurrentLocale();
        }
        return $this->currentLocale;
    }
    
    /**
     * Sets the current locale to the given instance.
     * @param Locale|string|Stringable $locale
     * @param bool|null $persist If true, the locale will be persisted in the session. False or null will not persist it (default).
     * @return void
     * @throws SettingUnavailableLocaleException if the given locale is not in the list of active locales.
     */
    public function setCurrentLocale(Locale|string|\Stringable $locale, ?bool $persist = null): void
    {
        $validatedLocale = $this->resolveLocaleObject($locale);
        
        if ($validatedLocale === null) {
            throw new SettingUnavailableLocaleException(
                $locale,
                $this->activeLocaleIds,
            );
        }
        
        if ($persist && !$this->application->runningInConsole()) {
            $this->setSessionLocale($validatedLocale);
            $this->setLastLanguageCookieLocale($validatedLocale);
        }
        
        $this->currentLocale = $validatedLocale;
        $this->application->setLocale($validatedLocale->lang);
    }
    
    /**
     * Returns the instance of the default locale.
     * @return Locale
     */
    public function getDefaultLocale(): Locale
    {
        return $this->resolveLocaleObject($this->defaultLocale);
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
     * @param Locale|string|null $locale
     * @return Locale
     */
    public function getMostLikelyLocale(Locale|string|null $locale = null): Locale
    {
        // Not 100% sure about this to be honest...
        // If a Locale is given, we use its id; this means even if the appLocale is given as a Locale,
        // we do treat it as if no locale was given. This is to be consistent with the handling of strings below.
        if ($locale instanceof Locale) {
            $locale = $locale->lang;
        }
        
        // Because of historic reasons, we do not use the app locale, so if the given locale IS the app locale,
        // we treat it as if no locale was given.
        if ($locale === $this->application->getLocale()) {
            $locale = null;
        }
        
        return $this->resolveLocaleObject($locale) ?? $this->getCurrentLocale();
    }
    
    /**
     * Sets the given locale in the session.
     * This is the locale that will be used as the current locale on the next request.
     * @param Locale $locale
     * @return void
     * @throws SettingUnavailableLocaleException if the given locale is not in the list of active locales.
     */
    private function setSessionLocale(Locale $locale): void
    {
        if ($this->application->runningInConsole()) {
            return;
        }
        
        $this->session->put(self::SESSION_LANGUAGE_KEY, $locale->toLegacyArray());
    }
    
    /**
     * Returns the locale stored in the session, or null if none is set or if the stored locale is not active.
     * @return Locale|null
     */
    private function getSessionLocale(): Locale|null
    {
        if ($this->application->runningInConsole()) {
            return null;
        }
        
        $locale = $this->session->get(self::SESSION_LANGUAGE_KEY);
        
        // This should in theory always be an array or null, but we check for string just in case
        if (is_string($locale)) {
            $localeObj = $this->resolveLocaleObject($locale);
            if ($localeObj !== null) {
                return $localeObj;
            }
        }
        
        $locale = is_array($locale) ? $locale['id'] ?? null : $locale;
        return $this->resolveLocaleObject($locale);
    }
    
    /**
     * Resolves the current locale by checking the session, then the lastLanguage_cookie, and finally falling back to the default locale.
     * The resolved locale is stored in $this->resolvedCurrentLocale.
     * @return void
     */
    private function resolveCurrentLocale(): void
    {
        $this->currentLocale = $this->getSessionLocale()
            ?? $this->getLastLanguageCookieLocale()
            ?? $this->getDefaultLocale();
    }
    
    /**
     * Returns the locale stored in the lastLanguage_cookie, or null if none is set or if the stored locale is not active.
     * @return Locale|null
     */
    private function getLastLanguageCookieLocale(): Locale|null
    {
        if ($this->application->runningInConsole()) {
            return null;
        }
        
        $request = $this->application->get(Request::class);
        if (!$request instanceof Request) {
            return null;
        }
        
        $cookieValue = $request->cookie(self::LAST_LANGUAGE_COOKIE_KEY);
        if (empty($cookieValue) || !is_string($cookieValue)) {
            return null;
        }
        return $this->resolveLocaleObject($cookieValue);
    }
    
    /**
     * Sets the lastLanguage_cookie to the given locale.
     * The cookie will be stored for 120 days.
     * @param Locale $locale
     * @return void
     */
    private function setLastLanguageCookieLocale(Locale $locale): void
    {
        if ($this->application->runningInConsole()) {
            return;
        }
        
        $this->cookieJar->queue(self::LAST_LANGUAGE_COOKIE_KEY, (string)$locale, 60 * 24 * 120); // Store cookie for 120 days
    }
    
    /**
     * Given a locale as a string, returns the corresponding Locale instance, or null if not found.
     * @param string|\Stringable|null $locale
     * @return Locale|null
     */
    private function resolveLocaleObject(string|\Stringable|null $locale): Locale|null
    {
        if ($locale === null) {
            return null;
        }
        
        $locale = strtolower((string)$locale);
        
        foreach ($this->locales as $configuredLocale) {
            if (strtolower($configuredLocale->lang) === $locale) {
                return $configuredLocale;
            }
        }
        
        return null;
    }
}
