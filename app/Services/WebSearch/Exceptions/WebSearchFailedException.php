<?php

declare(strict_types=1);

namespace App\Services\WebSearch\Exceptions;

class WebSearchFailedException extends \RuntimeException implements WebSearchExceptionInterface
{
    private function __construct(string $reason, ?\Throwable $previous = null)
    {
        parent::__construct("WebSearch failed: $reason", 500, $previous);
    }

    public static function missingConfiguration(string $provider): self
    {
        return new self("{$provider} web search configuration is missing.");
    }

    public static function connectionFailed(string $provider, \Throwable $previous): self
    {
        return new self("{$provider} connection error: " . $previous->getMessage(), $previous);
    }

    public static function noDefaultProvider(): self
    {
        return new self('No web search provider is configured (set WEB_SEARCH_PROVIDER).');
    }

    public static function invalidDefaultProvider(string $provider): self
    {
        return new self("Invalid default web search provider '{$provider}'.");
    }
}
