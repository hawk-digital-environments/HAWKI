<?php
declare(strict_types=1);

namespace App\Services\Ai\Exceptions;

class InvalidTransferIdException extends \InvalidArgumentException implements AiExceptionInterface
{
    public static function forUnknownTransferId(string $transferId): self
    {
        return new self(sprintf(
            'No active transfer found for transfer ID "%s". The ID may have already been consumed or was never registered.',
            $transferId
        ));
    }
}
