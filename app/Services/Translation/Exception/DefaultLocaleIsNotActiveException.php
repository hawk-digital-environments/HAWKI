<?php
declare(strict_types=1);


namespace App\Services\Translation\Exception;


class DefaultLocaleIsNotActiveException extends \RuntimeException implements TranslationExceptionInterface
{
    public function __construct(
        string $defaultLocale,
        array  $activeLocaleIds,
    )
    {
        parent::__construct("The default locale '$defaultLocale' is not in the list of active locales: [" . implode(', ', $activeLocaleIds) . "]");
    }
    
}
