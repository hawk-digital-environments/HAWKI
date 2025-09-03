<?php
declare(strict_types=1);


namespace App\Services\AI\Exception;


use App\Services\AI\Interfaces\ClientInterface;

class InvalidClientClassException extends \TypeError implements AiServiceExceptionInterface
{
    public function __construct(
        string $brokenClass,
    )
    {
        parent::__construct(sprintf(
            'The AI client class "%s" is invalid. It must implement the %s.',
            $brokenClass,
            ClientInterface::class
        ));
    }
}
