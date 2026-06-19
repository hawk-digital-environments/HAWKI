<?php
declare(strict_types=1);


namespace App\Services\Ai\Values;

/**
 * Capabilities are a "generic concept" to define features across various providers.
 * OpenAi calls them "provider functions", for Anthropic they are tools, etc.
 * You can configure how the capabilities are handled (e.g. web_search by using a tool or natively) using the {@see AiModelSetting} with the keys defined in {@see WellKnownModelSettings}.
 *
 * Note: In general, all capabilites are "tools", so your model's {@see WellKnownModelSettings::TOOL_CALLING} option must be set to TRUE to use any of these capabilities.
 * This is true for both "native" and "tool" capabilities, as even the "native" ones are implemented as tools under the hood.
 */
interface WellKnownCapabilities
{
    /**
     * Defines if the model has access to a web search capability.
     */
    public const WEB_SEARCH = 'web_search';

    /**
     * This is more or less a "RAG of your knowledge base" capability.
     * If you have connected your HAWKI instance to a knowledge base, you can use this capability to allow the model to query it.
     * You must register a tool with the "knowledge_base" capability for this to work.
     */
    public const KNOWLEDGE_BASE = 'knowledge_base';
}
