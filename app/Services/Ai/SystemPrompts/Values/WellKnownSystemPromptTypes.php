<?php
declare(strict_types=1);


namespace App\Services\Ai\SystemPrompts\Values;


interface WellKnownSystemPromptTypes
{
    /**
     * The default prompt for new chats.
     */
    public const string DEFAULT = 'default';
    /**
     * A prompt for summarizing conversations.
     */
    public const string SUMMARY = 'summary';
    /**
     * A prompt for improving user inputs. Mostly for improving prompts to get better results from LLMs.
     */
    public const string PROMPT_IMPROVEMENT = 'prompt_improvement';
    /**
     * A prompt for generating a title for a chat
     */
    public const string TITLE_GENERATION = 'title_generation';
}
