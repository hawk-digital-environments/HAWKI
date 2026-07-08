<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Capabilities\Values;


use App\Services\Ai\Utils\AbstractTagList;

/**
 * Tag list of capabilities that an AI model supports natively on the provider side.
 *
 * "Native" capabilities are features the provider itself implements (e.g. built-in web
 * search or code execution), as opposed to capabilities implemented on the HAWKI side
 * via tool calls. Stored as the `native_capabilities` JSON column on
 * {@see \App\Models\Ai\AiModel}.
 *
 * Well-known capability keys are defined in {@see WellKnownCapabilities}.
 */
final class NativeAiModelCapabilities extends AbstractTagList
{
    // -------------------------------------------------------
    // Well known capabilities
    // -------------------------------------------------------

    public function hasWebSearch(): bool
    {
        return $this->has(WellKnownCapabilities::WEB_SEARCH);
    }

    public function hasWebFetch(): bool
    {
        return $this->has(WellKnownCapabilities::WEB_FETCH);
    }

    public function hasCodeExecution(): bool
    {
        return $this->has(WellKnownCapabilities::CODE_EXECUTION);
    }

    public function hasToolCalling(): bool
    {
        return $this->has(WellKnownCapabilities::TOOL_CALLING);
    }
}
