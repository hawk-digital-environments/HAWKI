<?php
declare(strict_types=1);


namespace App\Services\AI\Exception;


class NoContextBoundException extends \RuntimeException implements AiServiceExceptionInterface
{
    public function __construct(
        string $modelId,
        string $executedMethod
    )
    {
        parent::__construct(sprintf(
            'No context is bound to the model "%s". Please bind a context before executing "%s".',
            $modelId,
            $executedMethod
        ));
    }
}
