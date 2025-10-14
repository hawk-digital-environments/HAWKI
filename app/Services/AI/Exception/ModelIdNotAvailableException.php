<?php
declare(strict_types=1);


namespace App\Services\AI\Exception;


class ModelIdNotAvailableException extends \RuntimeException implements AiServiceExceptionInterface
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
