<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Ai\Models\Capabilities\AiModelCapabilityRegistry;
use App\Services\Config\Registries\PublicConfigRegistry;
use App\Services\Rag\Citations\RagCitationCollector;
use App\Services\Rag\Config\RagConfig;
use App\Services\Rag\Contracts\RagIngesterInterface;
use App\Services\Rag\Implementations\HawkiRagIngester;
use App\Services\Rag\Implementations\NullRagIngester;
use Illuminate\Support\ServiceProvider;

/**
 * Assembles the RAG module's service core — assistant-free by design:
 *
 * - the ingestion contract is bound to the configured backend driver,
 *   mirroring the FileConverterServiceProvider pattern (a null/unknown
 *   driver resolves to a no-op implementation instead of failing at boot);
 * - the module's config object ({@see RagConfig}) is declared in the public
 *   config registry so the frontend learns whether RAG is enabled;
 * - the knowledge_base capability's UI metadata is declared in the model
 *   capability registry — it follows the tool rows the module's seeder
 *   (RagToolSeeder) ships, not the assistant adoption;
 * - the per-request citation collector is scoped: written by the RAG
 *   citation listener while MCP tools execute, drained by the stream
 *   controller when the stream ends.
 *
 * The Assistant host's adoption of RAG (the ambient knowledge agent tool
 * and the attachment ingestion workflow) lives in the separate
 * {@see AssistantKnowledgeServiceProvider} composition slice — so this
 * provider and the rest of the Rag slice stay free of Assistant imports,
 * and at plugin-extraction time the core ships as the rag plugin while
 * the composition becomes the bridge requiring both plugins.
 */
class RagServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RagIngesterInterface::class, static function ($app): RagIngesterInterface {
            $config = $app->make(RagConfig::class);

            return match ($config->driver) {
                'hawki_rag' => $app->make(HawkiRagIngester::class),
                default => $app->make(NullRagIngester::class),
            };
        });

        $this->app->scoped(RagCitationCollector::class);
    }

    public function boot(): void
    {
        $this->app->extend(PublicConfigRegistry::class, static function (PublicConfigRegistry $registry): PublicConfigRegistry {
            return $registry->declare(RagConfig::class);
        });

        $this->app->extend(AiModelCapabilityRegistry::class, static function (AiModelCapabilityRegistry $registry): AiModelCapabilityRegistry {
            return $registry->declare(
                key: WellKnownCapabilities::KNOWLEDGE_BASE,
                titleTranslationLabel: 'chat.composer.toolMenu.tools.knowledgeBase',
                descriptionTranslationLabel: 'chat.composer.toolMenu.tools.knowledgeBaseDescription',
                iconPath: resource_path('icons/tools/knowledge-base.svg'),
            );
        });
    }
}
