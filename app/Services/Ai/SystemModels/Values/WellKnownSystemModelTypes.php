<?php
declare(strict_types=1);


namespace App\Services\Ai\SystemModels\Values;


interface WellKnownSystemModelTypes
{
    /**
     * The default chat model if none was previously specified. This is the model that will be used for all chat requests unless a different model is spelected.
     */
    public const string DEFAULT = 'default';
    /**
     * This model is used for generating titles for chats based on the first message in the chat.
     * Generally speaking this can be a smaller/cheap model since it is only used for generating titles and not for actual chat responses.
     */
    public const string TITLE_GENERATION = 'title_generation';
    /**
     * This model is used for improving prompts. It can be used to improve the quality of prompts before sending them to the actual chat model.
     */
    public const string PROMPT_IMPROVEMENT = 'prompt_improvement';
    /**
     * This model is used for summarizing chats. It can be used to generate summaries of chats for display in the chat list or for other purposes.
     */
    public const string SUMMARY = 'summary';
}
