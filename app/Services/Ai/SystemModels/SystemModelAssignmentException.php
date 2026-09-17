<?php

declare(strict_types=1);

namespace App\Services\Ai\SystemModels;

final class SystemModelAssignmentException extends \DomainException
{
    public const UNAVAILABLE = 'unavailable';

    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
