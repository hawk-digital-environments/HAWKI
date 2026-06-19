<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class ModelIdNotAvailableException extends \RuntimeException implements AiExceptionInterface
{
    public function __construct(
        string $modelId
    )
    {
        parent::__construct(sprintf(
            'The model ID "%s" is not available.',
            $modelId
        ));
    }
}
