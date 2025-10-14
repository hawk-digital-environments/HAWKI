<?php
declare(strict_types=1);


namespace App\Services\AI\Exception;


class MissingRequiredAiServiceClassException extends \RuntimeException implements AiServiceExceptionInterface
{
    public function __construct(
        string $missingClass
    )
    {
        parent::__construct(sprintf(
            'The AI service class "%s" is required but missing. Please ensure it is properly defined and accessible.',
            $missingClass
        ));
    }
}
