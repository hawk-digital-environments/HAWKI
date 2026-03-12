<?php
declare(strict_types=1);

namespace App\Services\AI\Db;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use Illuminate\Config\Repository;
use Illuminate\Container\Attributes\Singleton;

/**
 * Syncs AI model & provider configuration (model_providers.php + model_lists/*.php)
 * into the database so the DB mirrors the active config at all times.
 *
 * Rules:
 * - Providers and models are created or updated (updateOrCreate).
 * - Existing DB records are never deleted; operators can deactivate via the DB directly.
 * - The `active` flag is always overridden from config so that env-variable changes
 *   take effect on the next sync.
 */
#[Singleton]
class AiModelSyncService
{
    public function __construct(
        private readonly Repository $config
    ) {}

    /**
     * Run the full sync and return a summary.
     *
     * @return array{providers_synced: int, models_synced: int}
     */
    public function sync(): array
    {
        $stats = ['providers_synced' => 0, 'models_synced' => 0];

        foreach ($this->config->get('model_providers.providers', []) as $providerId => $rawConfig) {
            $provider = AiProvider::updateOrCreate(
                ['provider_id' => $providerId],
                [
                    'name'     => ucfirst($providerId),
                    'active'   => (bool) ($rawConfig['active'] ?? false),
                    'api_url'  => $rawConfig['api_url'] ?? null,
                    'ping_url' => $rawConfig['ping_url'] ?? null,
                ]
            );
            $stats['providers_synced']++;

            foreach ($rawConfig['models'] ?? [] as $modelConfig) {
                $modelId = $modelConfig['id'] ?? null;
                if (empty($modelId)) {
                    continue;
                }

                AiModel::updateOrCreate(
                    ['model_id' => $modelId],
                    [
                        'active'         => (bool) ($modelConfig['active'] ?? true),
                        'label'          => $modelConfig['label'] ?? $modelId,
                        'input'          => $modelConfig['input'] ?? ['text'],
                        'output'         => $modelConfig['output'] ?? ['text'],
                        'tools'          => $modelConfig['tools'] ?? [],
                        'default_params' => $modelConfig['default_params'] ?? [],
                        'provider_id'    => $provider->id,
                    ]
                );
                $stats['models_synced']++;
            }
        }

        return $stats;
    }

    /**
     * Returns true when the ai_models table has at least one row.
     * Used by the service provider to decide whether an initial sync is needed.
     */
    public function isSynced(): bool
    {
        try {
            return AiModel::exists();
        } catch (\Exception) {
            return false;
        }
    }
}
