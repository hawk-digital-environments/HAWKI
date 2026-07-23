<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Repositories;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Values\ProviderSettings;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use Illuminate\Support\Collection;

/**
 * Database access layer for the {@see AiProvider} model.
 *
 * Encapsulates all Eloquent queries related to AI providers so that services never
 * call model statics directly.  Contextual scopes (e.g. the active-filter scope) are
 * respected by default; pass a {@see ScopeOverrides} instance to bypass specific
 * scopes for a single query (e.g. an admin view that must see inactive providers too).
 */
class AiProviderRepository extends AbstractRepositoryWithContextualScopes
{
    /**
     * Finds a single provider by its string identifier (the config key, e.g. `'openAi'`).
     *
     * Returns `null` when no record matches, leaving the caller free to decide whether
     * the absence is an error.
     */
    public function findOneByProviderId(string $providerId, ?ScopeOverrides $scopeOverrides = null): ?AiProvider
    {
        return $this->getQuery($scopeOverrides)->where('provider_id', $providerId)->first();
    }

    /**
     * Returns all providers whose `active` flag is `true`.
     *
     * Used by background jobs such as the model status updater to know which providers
     * should be polled.  Pass {@see makeScopeOverrides()} to bypass additional contextual
     * scopes (e.g. tenant isolation) when a privileged caller needs the full list.
     *
     * @return Collection<int, AiProvider>
     */
    public function findAllActive(?ScopeOverrides $scopeOverrides = null): Collection
    {
        return $this->getQuery($scopeOverrides)->where('active', true)->get();
    }

    /**
     * Creates or updates a provider record keyed on `$providerId`.
     *
     * Designed for the config-file sync flow: called once per provider entry in the YAML
     * config; creates the row on first sync, updates it on subsequent runs.  All columns
     * are overwritten on every call, so removing a value from config sets the column to
     * `null` in the database.
     */
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
