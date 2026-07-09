<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters\Traits\OpenAiModelListTraitTestFixtures;

use App\Services\Ai\Providers\Adapters\ModelList\ModelListClient;
use App\Services\Ai\Providers\Adapters\Traits\OpenAiModelListTrait;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Support\Collection;

/**
 * Thin wrapper that exposes the protected fetchOpenAiModelList method for unit testing.
 */
class OpenAiModelListTraitStub
{
    use OpenAiModelListTrait;

    public function fetch(
        AiProviderProxy $provider,
        ModelListClient $client,
        string|null     $alternativeRoute = null,
        \Closure|null   $alternativeMapper = null,
    ): Collection {
        return $this->fetchOpenAiModelList($provider, $client, $alternativeRoute, $alternativeMapper);
    }
}
