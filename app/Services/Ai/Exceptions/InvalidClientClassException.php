<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


use App\Services\Ai\Contracts\ClientInterface;

class InvalidClientClassException extends \TypeError implements AiExceptionInterface
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
