<?php
declare(strict_types=1);


namespace App\Services\Translation\Exception;


class SettingUnavailableLocaleException extends \InvalidArgumentException implements TranslationExceptionInterface
{
    public function __construct(
        string $locale,
        array  $configuredLocales,
    )
    {
        parent::__construct("The locale '$locale' is not in the list of configured and ACTIVE locales: [" . implode(', ', $configuredLocales) . "]");
    }
    
}
