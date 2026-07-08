<?php
declare(strict_types=1);

namespace App\Services\ExternalContent\Exceptions;

class FailedToFetchUrlException extends \RuntimeException implements ExternalContentExceptionInterface
{
    public static function forUrl(string $url): self
    {
        return new self(sprintf('Failed to fetch external URL "%s".', $url));
    }
}
