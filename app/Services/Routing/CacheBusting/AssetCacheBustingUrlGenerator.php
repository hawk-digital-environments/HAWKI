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
