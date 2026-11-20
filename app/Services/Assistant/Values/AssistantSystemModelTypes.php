<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

/**
 * The system-model slot names the Assistant slice contributes. The slice
 * owns its vocabulary and declares the assignments into the Ai core's
 * {@see \App\Services\Ai\SystemModels\SystemModelRegistry} from its own
 * service provider — the core stays unaware of them (see
 * WellKnownSystemModelTypes for the core's own slots).
 */
interface AssistantSystemModelTypes
{
    /**
     * The model that runs the assistant builder's AI guide, which walks
     * creators through setting up an assistant and fills the builder fields
     * via structured output. It needs to follow long instructions and
     * produce reliable structured output, so a capable model is recommended.
     */
    public const string GUIDE = 'assistant_guide';
}
