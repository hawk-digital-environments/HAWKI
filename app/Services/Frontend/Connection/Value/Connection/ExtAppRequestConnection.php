<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Value\Connection;


use App\Services\Frontend\Connection\Value\LocaleConfig;
use App\Utils\JsonSerializableTrait;

readonly class ExtAppRequestConnection implements \JsonSerializable
{
    use JsonSerializableTrait;
    
    public function __construct(
        public string       $version,
        public LocaleConfig $locale,
        public string       $connectUrl
    )
    {
    }
}
