<?php

declare(strict_types=1);

namespace App\Services\WebSearch\Implementations;

use App\Services\WebSearch\Contracts\WebSearchInterface;
use App\Services\WebSearch\Exceptions\WebSearchFailedException;
use App\Services\WebSearch\Values\WebSearchResponse;
use App\Services\WebSearch\Values\WebSearchResult;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * @see https://api-dashboard.search.brave.com/api-reference/web/search/get
 */
class BraveSearch implements WebSearchInterface
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly ConfigRepository $config,
    ) {
    }

    public function search(string $query, int $maxResults = 5): WebSearchResponse
    {
        $apiKey = $this->configValue('api_key');
        $apiUrl = $this->configValue('api_url');

        try {
            $data = $this->http->timeout(20)
                ->acceptJson()
                ->withHeaders(['X-Subscription-Token' => $apiKey])
                ->get($apiUrl, [
                    'q' => $query,
                    'count' => $maxResults,
                ])
                ->throw()
                ->json();
        } catch (\Throwable $exception) {
            throw WebSearchFailedException::connectionFailed('Brave', $exception);
        }

        $results = [];
        foreach ($data['web']['results'] ?? [] as $item) {
            if (!is_array($item) || empty($item['url'])) {
                continue;
            }

            // Brave highlights matches with inline <strong> tags.
            $results[] = new WebSearchResult(
                title: strip_tags((string)($item['title'] ?? '')),
                url: (string)$item['url'],
                content: strip_tags((string)($item['description'] ?? '')),
            );
        }

        return new WebSearchResponse($query, $results);
    }

    private function configValue(string $key): string
    {
        $value = trim((string)$this->config->get("web_search.services.brave.$key", ''));
        if ($value === '') {
            throw WebSearchFailedException::missingConfiguration('Brave');
        }

        return $value;
    }
}
