<?php
declare(strict_types=1);


namespace App\Services\Translation;


use App\Services\Translation\Loader\TranslationLoaderInterface;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Translation\FileLoader;

class LaravelTranslationLoaderAdapter implements Loader
{
    /**
     * The list of loaded translations, as a list of locale ids to translation arrays.
     * @var array
     */
    protected array $loadedByLocale = [];
    
    /**
     * The list of loaded fallback translations, as a list of namespace to translation arrays.
     * This is only loaded once per namespace, as the fallback locale is always 'en'.
     * @var array
     */
    protected array $loadedFallbackDataByNamespace = [];
    
    /**
     * The list of merged translations, as a list of namespace|localeId to translation arrays.
     * @var array
     */
    protected array $mergedByNamespace = [];

    public function __construct(
        protected TranslationLoaderInterface $loader,
        protected FileLoader                 $fallbackLoader,
        protected LocaleService              $translationService
    )
    {
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
    public function load($locale, $group, $namespace = null): array
    {
        $localeObj = $this->translationService->getMostLikelyLocale($locale);
        $localeId = $localeObj->lang;
        
        $isCatchAllGroup = $group === '*';
        $namespaceKey = $group . '|' . ($namespace ?? '*');
        $mergedKey = $namespaceKey . '|' . $group . '|' . $localeId;
        
        if (!isset($this->mergedByNamespace[$mergedKey])) {
            if (!isset($this->loadedByLocale[$localeId])) {
                $this->loadedByLocale[$localeId] = $this->loader->load($localeObj);
            }
            
            if (!isset($this->loadedFallbackDataByNamespace[$namespaceKey])) {
                $fallbackList = $this->fallbackLoader->load('en', $group, $namespace);
                if ($isCatchAllGroup) {
                    $this->loadedFallbackDataByNamespace[$namespaceKey] = $fallbackList;
                } else {
                    $this->loadedFallbackDataByNamespace[$namespaceKey] = [$group => $fallbackList];
                }
            }
            
            $this->mergedByNamespace[$mergedKey] = $this->mergeArrayRecursive(
                $this->loadedFallbackDataByNamespace[$namespaceKey],
                $this->loadedByLocale[$localeId]
            );
        }
        
        $merged = $this->mergedByNamespace[$mergedKey];
        
        if (!$isCatchAllGroup && isset($merged[$group])) {
            return $merged[$group];
        }
        
        return $merged;
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
    
    private function mergeArrayRecursive(array $a, array $b): array
    {
        foreach ($b as $key => $value) {
            if (is_array($value) && isset($a[$key]) && is_array($a[$key])) {
                $a[$key] = $this->mergeArrayRecursive($a[$key], $value);
            } else {
                $a[$key] = $value;
            }
        }
        return $a;
    }
}
