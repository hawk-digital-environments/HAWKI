<?php
declare(strict_types=1);


namespace App\Services\Ai\Values;

interface WellKnownModelSettings
{
    /**
     * How many rounds of tool calling (tool calls that trigger more tool calls) are allowed before giving up. This is to prevent infinite loops in tool calling.
     */
    public const MAX_TOOL_CALLING_ROUNDS_STREAMING = 'max_tool_calling_rounds_streaming';
    /**
     * How many rounds of tool calling (tool calls that trigger more tool calls) are allowed before giving up. This is to prevent infinite loops in tool calling.
     */
    public const MAX_TOOL_CALLING_ROUNDS = 'max_tool_calling_rounds';
    /**
     * Defines if it is allowed to upload files for this model.
     */
    public const FILE_UPLOAD = 'file_upload';
    /**
     * Defines if this model is allowed to be used "agentic", meaning that it can call tools.
     */
    public const TOOL_CALLING = 'tool_calling';
}
