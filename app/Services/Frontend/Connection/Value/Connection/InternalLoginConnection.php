<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value\Connection;


use App\Services\Frontend\Connection\Value\LocaleConfig;
use App\Services\Frontend\Connection\Value\TranslatorConfig;
use App\Utils\JsonSerializableTrait;

/**
 * Super lightweight version of {@see InternalConnection} that is used during the login process,
 * before the user is authenticated and we can fetch all the user-specific data. It only contains
 * the data that is needed to render the login page and perform the login request.
 */
readonly class InternalLoginConnection implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(
        public string           $version,
        public LocaleConfig     $locale,
        public TranslatorConfig $translation
    )
    {
    }
}
