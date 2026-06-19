<?php

namespace App\Services\Ai\Values;

/**
 * @deprecated Will be replaced with a "WellKnownSystemPromptTypes" interface, so plugins can also define their own.
 * @todo Remove this enum and replace all usages with the new interface.
 */
enum SystemPromptType: string
{
    /**
     * The default prompt for new chats.
     */
    case DEFAULT = 'default';
    /**
     * A prompt for summarizing conversations.
     */
    case SUMMARY = 'summary';
    /**
     * A prompt for improving user inputs. Mostly for improving prompts to get better results from LLMs.
     */
    case PROMPT_IMPROVEMENT = 'prompt_improvement';
    /**
     * A prompt for generating a title for a chat
     */
    case TITLE_GENERATION = 'title_generation';
}
