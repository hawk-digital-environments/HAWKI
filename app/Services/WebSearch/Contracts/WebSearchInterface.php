<?php

declare(strict_types=1);

namespace App\Services\WebSearch\Contracts;

use App\Services\WebSearch\Exceptions\WebSearchFailedException;
use App\Services\WebSearch\Values\WebSearchResponse;

/**
 * Backend-agnostic web search contract.
 *
 * Implementations encapsulate the provider's wire format and normalize the
 * response into a {@see WebSearchResponse}, so consumers (e.g. the
 * WebSearchTool) never depend on a specific search API.
 */
interface WebSearchInterface
{
    /**
     * @throws WebSearchFailedException when the provider is misconfigured or the request fails.
     */
    public function search(string $query, int $maxResults = 5): WebSearchResponse;
}
