<?php
declare(strict_types=1);


namespace App\Services\AI\Exception;


class ModelNotInPayloadException extends \InvalidArgumentException implements AiServiceExceptionInterface
{
    public function __construct(
        array $payload
    )
    {
        parent::__construct(
            sprintf('The payload [%s] does not contain a "model" key.', implode(', ', array_keys($payload))),
        );
    }
}
