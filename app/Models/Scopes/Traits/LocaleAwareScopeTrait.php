<?php
declare(strict_types=1);


namespace App\Models\Scopes\Traits;


use App\Services\Translation\LocaleService;
use App\Services\Translation\Value\Locale;

trait LocaleAwareScopeTrait
{
    use ServiceLocatingScopeTrait;

    private \Closure $last_currentLocaleResolver;
    private \Closure $last_defaultLocaleResolver;

    public function initializeLocaleAwareScopeTrait(LocaleService $localeService): void
    {
        $this->last_currentLocaleResolver = static fn() => $localeService->getCurrentLocale();
        $this->last_defaultLocaleResolver = static fn() => $localeService->getDefaultLocale();
    }

    public function withCurrentLocaleResolver(\Closure $resolver): static
    {
        $this->last_currentLocaleResolver = $resolver;
        return $this;
    }

    public function withDefaultLocaleResolver(\Closure $resolver): static
    {
        $this->last_defaultLocaleResolver = $resolver;
        return $this;
    }

    public function getCurrentLocale(): Locale
    {
        return $this->serviceLocator->call('localeAwareScope.currentLocale', $this->last_currentLocaleResolver);
    }

    public function getDefaultLocale(): Locale
    {
        return $this->serviceLocator->call('localeAwareScope.defaultLocale', $this->last_defaultLocaleResolver);
    }
}
