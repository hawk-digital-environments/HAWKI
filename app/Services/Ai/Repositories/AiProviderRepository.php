<?php
declare(strict_types=1);


namespace App\Services\Ai\Repositories;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Values\ProviderSettings;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use Illuminate\Support\Collection;


class AiProviderRepository extends AbstractRepositoryWithContextualScopes
{
    /**
     * @return Collection<int, AiProvider>
     */
    public function findAllActive(?ScopeOverrides $scopeOverrides = null): Collection
    {
        return $this->getQuery($scopeOverrides)->where('active', true)->get();
    }

    public function upsert(
        string            $providerId,
        string            $adapterKey,
        string            $name,
        bool              $active,
        ?string           $apiUrl,
        ?string           $modelStatusUrl,
        ?string           $apiKey,
        ?ProviderSettings $settings,
    ): AiProvider
    {
        return $this->getQueryWithoutAnyScopes()
            ->updateOrCreate(
                ['provider_id' => $providerId],
                [
                    'name' => $name,
                    'adapter_key' => $adapterKey,
                    'active' => $active,
                    'api_url' => $apiUrl,
                    'model_status_url' => $modelStatusUrl,
                    'api_key' => $apiKey,
                    'settings' => $settings
                ]
            );
    }
}
