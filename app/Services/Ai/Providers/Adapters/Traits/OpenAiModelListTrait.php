<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Traits;


use App\Services\Ai\ModelInformation\Enrichment\ModelInfoEnrichingTrait;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListClient;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Support\Collection;

/**
 * Shared model-list fetching logic for adapters that follow the OpenAI model-list API shape.
 *
 * Several providers (OpenAI, OpenRouter, Deepseek, …) expose a `/models` endpoint whose JSON
 * response has the shape `{ "data": [ { "id": "...", … }, … ] }`.  This trait centralises the
 * HTTP fetch, dot-path extraction, and default mapping into unsaved {@see \App\Models\Ai\AiModel}
 * instances so each adapter does not duplicate that boilerplate.
 *
 * Concrete adapters use this alongside {@see \App\Services\Ai\Providers\Adapters\AbstractProviderAdapter}:
 *
 * ```php
 * class OpenAiAdapter extends AbstractProviderAdapter
 * {
 *     use OpenAiModelListTrait;
 *
 *     public function getModels(AiProviderProxy $provider): Collection
 *     {
 *         return $this->fetchOpenAiModelList(
 *             $provider,
 *             $this->createModelListClient($this->client($provider->driver)),
 *         );
 *     }
 * }
 * ```
 *
 * @see \App\Services\Ai\Providers\Adapters\AbstractProviderAdapter::createModelListClient()
 */
trait OpenAiModelListTrait
{
    use ModelInfoEnrichingTrait;

    /**
     * Fetches the provider's model list via the OpenAI-compatible `/models` endpoint and maps
     * each entry into an unsaved {@see \App\Models\Ai\AiModel} instance.
     *
     * @param AiProviderProxy  $provider          Provider context forwarded to each created model info.
     * @param ModelListClient  $client            Pre-configured HTTP client for the provider.
     * @param string|null      $alternativeRoute  Override the default `/models` path when the provider
     *                                            uses a different endpoint (e.g. `/v1/models`).
     * @param \Closure|null    $alternativeMapper Replace the default `id`-based mapping closure when the
     *                                            response schema differs or additional fields must be extracted.
     *
     * @return Collection<int, \App\Models\Ai\AiModel>
     *
     * @see https://developers.openai.com/api/reference/resources/models/methods/list
     */
    protected function fetchOpenAiModelList(
        AiProviderProxy $provider,
        ModelListClient $client,
        string|null     $alternativeRoute = null,
        \Closure|null   $alternativeMapper = null
    ): Collection
    {
        return $client->get($alternativeRoute ?? '/models')
            ->getMapped(
                'data.*',
                $alternativeMapper ?? function ($item) use ($provider) {
                return $this->createNewModelInfo(
                    modelId: data_get($item, 'id'),
                    provider: $provider,
                );
            });
    }
}
