<?php
declare(strict_types=1);


namespace App\Services\AI\Exception;


class MissingKeyInProviderConfigException extends \InvalidArgumentException implements AiServiceExceptionInterface
{
    public function __construct(
        string $providerId,
        string $keyName
    )
    {
        parent::__construct("Missing required key '$keyName' in provider config for provider '$providerId'.");
    }
}
