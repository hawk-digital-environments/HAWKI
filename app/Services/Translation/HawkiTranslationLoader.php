<?php
declare(strict_types=1);


namespace App\Services\Translation;


use App\Http\Controllers\LanguageController;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Translation\Loader;

class HawkiTranslationLoader implements Loader
{
    protected array $loaded;

    public function __construct(
        protected LanguageController $languageController,
        protected Repository         $configRepository
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function load($locale, $group = null, $namespace = null): array
    {
        // As the translation handling in {@see LanguageController} is not using the default
        // Laravel language handling I can not reliably forward the parameters given to the loader.
        // Instead, I rely on the LanguageController to return the correct translations
        // which is not ideal but the only option I have without rewriting the whole translation handling.
        $this->loaded ??= $this->languageController->getTranslation();

        if (is_string($group) && $group !== '*') {
            if (isset($this->loaded[$group])) {
                return $this->loaded[$group];
            }
            return [];
        }

        return $this->loaded;
    }

    /**
     * @inheritDoc
     */
    public function addNamespace($namespace, $hint): void
    {
    }

    /**
     * @inheritDoc
     */
    public function addJsonPath($path): void
    {
    }

    /**
     * @inheritDoc
     */
    public function namespaces(): array
    {
        return [];
    }

}
