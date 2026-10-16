<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantFeedback;
use App\Services\Ai\Agents\AgentRegistry;
use App\Services\Ai\Agents\Implementations\Chat\ChatAgentFromLegacyRequestFactory;
use App\Services\Ai\Extensions\HawkiExtensionsRegistry;
use App\Services\Ai\SystemModels\SystemModelRegistry;
use App\Services\Ai\SystemModels\Values\WellKnownSystemModelTypes;
use App\Services\Assistant\Agents\AssistantChatAgentFactory;
use App\Services\Assistant\Config\AssistantConfig;
use App\Services\Assistant\Observers\AssistantFeedbackObserver;
use App\Services\Assistant\Observers\AssistantObserver;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Assistant slice into the application: model observers and the
 * assistant agent factory's declaration in the agent registry.
 *
 * Everything the slice needs from core flows through the established
 * extension points (the {@see AgentRegistry}); everything core needs from
 * the slice flows through the observers and the
 * {@see \App\Services\Assistant\AssistantRunComposer}. This provider is
 * deliberately the single wiring point so the slice can move into a plugin
 * package as-is — the future plugin contributes this provider through the
 * plugin system's service-provider mechanism.
 */
class AssistantServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->bootObservers();

        $this->declareAgentFactories();

        $this->declareSystemModels();
    }

    /**
     * Carries the builder guide's default model (owned by the Assistant
     * slice's config, {@see AssistantConfig}) into the system-model
     * registry the config syncer merges over its file-based configuration —
     * the builder's wiring stays in the slice, the registry stays the Ai
     * core's generic surface, and no provider mutates another domain's
     * config.
     */
    private function declareSystemModels(): void
    {
        $this->app->extend(
            SystemModelRegistry::class,
            fn (SystemModelRegistry $registry): SystemModelRegistry => $registry->declare(
                'assistant_guide',
                WellKnownSystemModelTypes::ASSISTANT_GUIDE,
                $this->app->make(AssistantConfig::class)->builderGuideModel,
            ),
        );
    }

    private function bootObservers(): void
    {
        Assistant::observe(AssistantObserver::class);
        AssistantFeedback::observe(AssistantFeedbackObserver::class);
    }

    /**
     * The assistant factory claims legacy-shaped requests carrying an
     * explicit assistant handle; it must run before the plain chat factory
     * so assistant-driven exchanges never fall through to it.
     */
    private function declareAgentFactories(): void
    {
        $this->app->extend(
            AgentRegistry::class,
            static fn (AgentRegistry $registry): AgentRegistry => $registry->declare(
                AssistantChatAgentFactory::class,
                before: ChatAgentFromLegacyRequestFactory::class,
            ),
        );

        $this->app->extend(
            HawkiExtensionsRegistry::class,
            static fn (HawkiExtensionsRegistry $registry): HawkiExtensionsRegistry => $registry->declare(
                'assistant_handle',
                [
                    'payload.hawkiExtensions' => ['nullable', 'string', 'max:255', 'exists:assistants,handle'],
                    'payload.messages.*.hawkiExtensions' => ['nullable', 'string', 'max:255'],
                ],
            ),
        );
    }
}
