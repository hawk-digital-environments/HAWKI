<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

/**
 * The assembled parameters of a single assistant-driven AI run.
 */
readonly class ComposedAssistantRun
{
    /**
     * @param string                                           $systemPrompt        fully composed system instructions (base prompt, setting fragments, answer-length module, knowledge files)
     * @param string                                           $modelId             the assistant's model id
     * @param bool                                             $allowModelSelect    whether a client-requested model may override the assistant's model
     * @param array{temp?: float, top_p?: float, max_tokens?: int} $params          sampling parameters for the run; empty values fall back to the model defaults downstream
     * @param list<string>                                     $toolTransferStrings HAWKI tool-transfer strings for the assistant's attached tools
     */
    public function __construct(
        public readonly string $systemPrompt,
        public readonly string $modelId,
        public readonly bool $allowModelSelect,
        public readonly array $params,
        public readonly array $toolTransferStrings,
    ) {
    }
}
