<?php
declare(strict_types=1);


namespace App\Services\Routing\CacheBusting;


use App\Utils\DecoratorTrait;
use Illuminate\Routing\UrlGenerator;

class AssetCacheBustingUrlGenerator extends UrlGenerator
{
    use DecoratorTrait;

    private CacheBusterGenerator $cacheBusterGenerator;

    public function setCacheBusterGenerator(CacheBusterGenerator $cacheBusterGenerator): void
    {
        $this->cacheBusterGenerator = $cacheBusterGenerator;
    }

    /**
     * @inheritDoc
     */
    public function asset($path, $secure = null): string
    {
        if ($this->isValidUrl($path)) {
            return $path;
        }

        // Vite build assets already contain a content hash in their filename,
        // so appending ?v= would cause ES module URLs to mismatch between the
        // script tag and inter-chunk imports, loading the module twice.
        if (str_starts_with(ltrim($path, '/'), 'build/')) {
            return parent::asset($path, $secure);
        }

        return $this->attachCacheBusterToUrl(
            parent::asset($path, $secure),
            $path
        );
    }

    private function attachCacheBusterToUrl(string $url, string $path): string
    {
        $cacheBuster = $this->cacheBusterGenerator->getCacheBusterFor($path);
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'v=' . $cacheBuster;
    }
}
