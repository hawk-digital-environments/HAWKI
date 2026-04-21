<?php
declare(strict_types=1);


namespace App\Services\ExtApp\Exception;


class FailedToResolveAppByIdException extends \RuntimeException implements AppExceptionInterface
{
    public function __construct(int $appId)
    {
        parent::__construct(
            sprintf('Failed to resolve app with ID %d', $appId)
        );
    }
}
