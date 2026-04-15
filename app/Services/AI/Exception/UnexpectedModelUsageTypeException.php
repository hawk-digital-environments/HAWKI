<?php

namespace App\Services\AI\Exception;

use App\Services\AI\Value\ModelUsageType;

class UnexpectedModelUsageTypeException extends \RuntimeException implements AiServiceExceptionInterface
{
    public static function forAvailableInUsageType(ModelUsageType $modelUsageType): self
    {
        return new self(
            sprintf(
                "Received an unknown usage type '%s'",
                $modelUsageType->value
            )
        );
    }
}
