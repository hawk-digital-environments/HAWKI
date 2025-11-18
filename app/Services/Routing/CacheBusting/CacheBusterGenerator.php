<?php
declare(strict_types=1);


namespace App\Services\Routing\CacheBusting;


use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;

#[Singleton]
readonly class CacheBusterGenerator
{
    private string $baseCacheBuster;

    public function __construct(
        #[Config('app.version')]
        private string      $versionString,
        #[Config('app.cache_buster')]
        private string|null $customCacheBuster,
        private Application $application,
    )
    {
        $this->baseCacheBuster = md5(
            implode('-', [
                $this->versionString,
                $this->customCacheBuster ?? 'default',
            ])
        );
    }

    /**
     * Get cache buster for a given file.
     * If the file exists, include its last modified timestamp.
     * Otherwise, return the base cache buster; which changes only when app version or custom cache buster changes.
     * @param string $file
     * @return string
     */
    public function getCacheBusterFor(string $file): string
    {
        $publicPath = $this->application->publicPath(ltrim($file, '/'));

        if (File::exists($publicPath)) {
            $timestamp = File::lastModified($publicPath);
            return md5($this->baseCacheBuster . '-' . $timestamp);
        }

        return $this->baseCacheBuster;
    }
}
