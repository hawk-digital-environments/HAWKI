<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Traits;


use App\Services\Ai\ModelInformation\Enrichment\ModelInfoEnrichingTrait;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListClient;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Support\Collection;

trait OpenAiModelListTrait
{
    use ModelInfoEnrichingTrait;

    protected function fetchOpenAiModelList(
        AiProviderProxy $provider,
        ModelListClient $client,
        string|null     $alternativeRoute = null,
        \Closure|null   $alternativeMapper = null
    ): Collection
    {
        /* @see https://developers.openai.com/api/reference/resources/models/methods/list */
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
