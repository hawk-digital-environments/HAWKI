<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Assistant\AgentToolRegistry;
use App\Services\Config\Registries\PublicConfigRegistry;
use App\Services\Rag\AssistantKnowledge\AgentTools\RagKnowledgeAgentTool;
use App\Services\Rag\Citations\RagCitationCollector;
use App\Services\Rag\Config\RagConfig;
use App\Services\Rag\Contracts\RagIngesterInterface;
use App\Services\Rag\Implementations\HawkiRagIngester;
use App\Services\Rag\Implementations\NullRagIngester;
use Illuminate\Support\ServiceProvider;

/**
 * Assembles the RAG slice — the single wiring point for everything RAG:
 *
 * - the ingestion contract is bound to the configured backend driver,
 *   mirroring the FileConverterServiceProvider pattern (a null/unknown
 *   driver resolves to a no-op implementation instead of failing at boot);
 * - the slice's config object ({@see RagConfig}) is declared in the public
 *   config registry so the frontend learns whether RAG is enabled;
 * - the knowledge-base agent tool is declared in the assistant agent-tool
 *   registry (the ambient capability granted to assistant runs);
 * - the per-request citation collector is scoped: written by the RAG
 *   citation listener while MCP tools execute, drained by the stream
 *   controller when the stream ends.
 *
 * The provider is eager rather than deferrable: the registry declarations
 * must run on every boot so the knowledge-base agent tool is declared
 * before any assistant run is composed. Like the Assistant slice, this
 * provider is deliberately self-contained so the slice can move into a
 * plugin package as-is.
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

        $this->app->extend(AgentToolRegistry::class, static function (AgentToolRegistry $registry): AgentToolRegistry {
            return $registry->declare(
                WellKnownCapabilities::KNOWLEDGE_BASE,
                RagKnowledgeAgentTool::class,
            );
        });
    }
}
