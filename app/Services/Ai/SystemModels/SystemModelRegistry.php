<?php

declare(strict_types=1);

namespace App\Services\Ai\SystemModels;

use App\Services\Ai\SystemModels\Values\WellKnownSystemModelTypes;
use Illuminate\Container\Attributes\Singleton;

/**
 * The registry of system-model assignments contributed by domains other
 * than the Ai core — the config-style extension point of the system-model
 * configuration: a slice owns its defaults in its own config file and
 * declares them here instead of writing into
 * `model_providers.system_models` (the file stays the Ai core's own).
 *
 * Wired like {@see \App\Services\Ai\Agents\AgentRegistry}: a singleton
 * whose declarations are added by service providers via
 * `$app->extend(SystemModelRegistry::class, ...)`.
 *
 * Usage (service provider):
 * ```php
 * $this->app->extend(
 *     SystemModelRegistry::class,
 *     fn(SystemModelRegistry $registry): SystemModelRegistry => $registry
 *         ->declare('assistant_guide', WellKnownSystemModelTypes::ASSISTANT_GUIDE, $config->builderGuideModel)
 * );
 * ```
 *
 * {@see \App\Services\Ai\ConfigFileSync\Syncers\SystemModelSyncer} merges
 * the entries over the file-based configuration, so a changed default
 * re-triggers the config sync (the entries are part of the sync hash).
 */
#[Singleton]
final class SystemModelRegistry
{
    /** @var array<string, array{type: string, defaultModelId: string|null}> */
    private array $entries = [];

    /**
     * Declares (or re-declares) the system-model assignment a domain
     * contributes. `key` is the configuration-style key the syncer maps
     * through {@see WellKnownSystemModelTypes} legacy upgrading.
     */
    public function declare(string $key, string $type, string|null $defaultModelId): self
    {
        $this->entries[$key] = ['type' => $type, 'defaultModelId' => $defaultModelId];

        return $this;
    }

    /**
     * The declared entries in declaration order: key => assignment.
     *
     * @return array<string, array{type: string, defaultModelId: string|null}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * The entries as the syncer's file-based shape: key => default model id.
     * A null default is omitted — no assignment is contributed.
     *
     * @return array<string, string>
     */
    public function systemModels(): array
    {
        $systemModels = [];

        foreach ($this->entries as $key => $entry) {
            if ($entry['defaultModelId'] !== null && $entry['defaultModelId'] !== '') {
                $systemModels[$key] = $entry['defaultModelId'];
            }
        }

        return $systemModels;
    }
}
