<?php
declare(strict_types=1);


namespace App\Services\System\Exceptions;


class NotImplementedException extends \LogicException implements SystemExceptionInterface
{
    public static function forReason(string $reason): self
    {
        return new self("Not implemented: {$reason}");
    }
}
