<?php
declare(strict_types=1);


namespace App\Services\AI\Exception;


use App\Services\AI\Interfaces\ClientInterface;

class IncorrectClientForRequestedModelException extends \RuntimeException implements AiServiceExceptionInterface
{
    public function __construct(
        ClientInterface $modelClient,
        ClientInterface $currentClient
    )
    {
        parent::__construct(sprintf(
            'The model is assigned to a different client. Model client: %s, Current client: %s',
            $modelClient::class,
            $currentClient::class
        ));
    }
}
