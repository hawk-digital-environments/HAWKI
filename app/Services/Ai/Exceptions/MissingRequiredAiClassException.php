<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class MissingRequiredAiClassException extends \RuntimeException implements AiExceptionInterface
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
