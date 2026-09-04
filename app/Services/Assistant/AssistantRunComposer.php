<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\Ai\AiTool;
use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Assistant\Values\ComposedAssistantRun;
use Illuminate\Container\Attributes\Singleton;

/**
 * Composes the AI run parameters for a chat exchange driven by an assistant:
 * the fully assembled system prompt, the assistant's model (and whether the
 * client may override it), the sampling parameters, and the tool-transfer
 * strings for the assistant's attached tools.
 *
 * This is the single assembly source for every assistant-driven surface —
 * both the agent factory for legacy-payload surfaces (private chat, group
 * chat) and the OpenAI responses endpoint derive their exchange from here.
 */
#[Singleton]
class AssistantRunComposer
{
    public function __construct(
        private readonly AssistantPromptComposer $promptComposer,
    ) {
    }

    public function compose(Assistant $assistant, ?User $actor = null): ComposedAssistantRun
    {
        $assistant->loadMissing(['ai_tools', 'attachments']);

        return new ComposedAssistantRun(
            systemPrompt: $this->promptComposer->compose($assistant, $actor),
            modelId: $assistant->model,
            allowModelSelect: $assistant->allow_model_select,
            params: array_filter([
                'temp' => $assistant->temp,
                'top_p' => $assistant->top_p,
                'max_tokens' => $assistant->max_tokens,
            ]),
            toolTransferStrings: $assistant->ai_tools
                ->map(static fn (AiTool $tool): string => $tool->name)
                ->values()
                ->all(),
        );
    }
}
