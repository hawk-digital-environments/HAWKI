<?php

declare(strict_types=1);

namespace App\Services\Assistant\Config;

use App\Services\Config\AbstractConfig;
use Illuminate\Config\Repository;

/**
 * The Assistant slice's configuration object — the single owner of every
 * `assistant.*` value (see `config/assistant.php` for the file-backed source
 * and the env variables behind it).
 *
 * Slice-internal consumers inject this class instead of reading the global
 * config repository, so the slice owns its configuration surface (the same
 * pattern as the Rag slice's {@see \App\Services\Rag\Config\RagConfig}).
 * Not published to the public config API: no frontend consumer exists for
 * these knobs.
 */
final class AssistantConfig extends AbstractConfig
{
    /** Consecutive assistant changes within this window merge into one version record. */
    public readonly int $assistantVersionsDebounceSeconds;

    /**
     * The builder guide's default model, declared into the system-model
     * registry (see {@see \App\Services\Ai\SystemModels\SystemModelRegistry});
     * admins override it per instance through the system-models UI.
     */
    public readonly string $builderGuideModel;

    public static function make(Repository $repo): static
    {
        return self::fromArray([
            'assistantVersionsDebounceSeconds' => (int) $repo->get('assistant.assistant_versions.debounce_seconds', 10),
            'builderGuideModel' => (string) $repo->get('assistant.builder.guide_model', 'gpt-5.6-luna'),
        ]);
    }
}
