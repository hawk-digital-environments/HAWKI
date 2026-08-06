<?php
declare(strict_types=1);


namespace App\Services\Routing\CacheBusting;


use App\Services\System\Time\CarbonClockInterface;
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
        string               $versionString,
        #[Config('app.cache_buster')]
        string|null          $customCacheBuster,
        private Application  $application,
        CarbonClockInterface $clock
    )
    {
        if ($this->application->isLocal()) {
            // In local environment, we want to disable cache busting to make development easier.
            $versionString = 'local-' . $clock->now()->getTimestamp();
        }

        $this->baseCacheBuster = md5(
            implode('-', [
                $versionString,
                $customCacheBuster ?? 'default',
            ])
        );
    }

    /**
     * Generates an ETag for a given local value. This can be used for API responses or any other content that needs cache validation.
     * "Local value" can be anything that represents the content, such as a version string, a timestamp, or a hash of the content itself.
     * It will be combined with the base cache buster to ensure that the ETag changes whenever the app version or custom cache buster changes, even if the local value remains the same.
     * @param string $localValue
     * @return string
     */
    public function getEtag(string $localValue): string
    {
        return md5($this->baseCacheBuster . '-' . $localValue);
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
