<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\WebSearch\Contracts\WebSearchInterface;
use App\Services\WebSearch\Exceptions\WebSearchFailedException;
use App\Services\WebSearch\Implementations\BraveSearch;
use App\Services\WebSearch\Implementations\TavilySearch;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the web search contract to the backend selected by `web_search.default`.
 * Resolution is lazy, so a missing/unknown provider only fails when a search is
 * actually run (surfacing as a tool error), never at boot.
 */
class WebSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WebSearchInterface::class, static function (Application $app): WebSearchInterface {
            $provider = trim((string)$app->make('config')->get('web_search.default', ''));

            return match ($provider) {
                'brave' => $app->make(BraveSearch::class),
                'tavily' => $app->make(TavilySearch::class),
                '' => throw WebSearchFailedException::noDefaultProvider(),
                default => throw WebSearchFailedException::invalidDefaultProvider($provider),
            };
        });
    }
}
