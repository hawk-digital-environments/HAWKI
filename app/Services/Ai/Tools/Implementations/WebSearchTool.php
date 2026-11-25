<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Implementations;

use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Ai\Tools\AbstractTool;
use App\Services\WebSearch\Contracts\WebSearchInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * Function-calling web search, backed by the provider configured in `config/web_search.php`.
 * Fulfils the `web_search` capability for models that have no native web search.
 */
class WebSearchTool extends AbstractTool
{
    public function __construct()
    {
        $this->setMaxRuns(5);
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'hawki_web_search';
    }

    /**
     * @inheritDoc
     */
    public function description(): string
    {
        return 'Searches the web for up-to-date information. Use it for current events, recent facts, '
            . 'or anything you are not certain about. Returns a list of results with title, url and content snippet.';
    }

    /**
     * @inheritDoc
     */
    public function capability(): string|null
    {
        return WellKnownCapabilities::WEB_SEARCH;
    }

    /**
     * @inheritDoc
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The search query. Be specific; use keywords rather than full sentences.')
                ->required(),
            'max_results' => $schema->integer()
                ->description('The maximum number of results to return')
                ->min(1)
                ->max(10)
                ->default(5),
        ];
    }

    /**
     * The search backend is resolved per call (not in the constructor), so a missing
     * provider configuration surfaces as a tool error instead of breaking tool loading.
     */
    public function __invoke(
        WebSearchInterface $webSearch,
        string             $query,
        int                $max_results = 5,
    ): array
    {
        $response = $webSearch->search($query, $max_results);

        if ($response->results === [] && $response->answer === null) {
            return [
                'response' => $response,
                'instruction' => 'The search returned no results. Tell the user, or retry once with a different query.',
            ];
        }

        return [
            'response' => $response,
            'instruction' => 'Base your answer on these results and cite the sources you use as markdown links with their url.',
        ];
    }
}
