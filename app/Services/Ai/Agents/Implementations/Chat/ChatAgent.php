<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Implementations\Chat;


use App\Services\Ai\Agents\Adapters\AbstractTextGeneratingAgent;
use App\Services\Ai\Agents\Values\AgentRequestContext;

class ChatAgent extends AbstractTextGeneratingAgent
{
    public function __construct(
        AgentRequestContext $context,
        string              $instructions,
        array               $messages,
        iterable            $tools,
        string|null         $promptString = null,
        array|null          $attachments = null
    )
    {
        parent::__construct(
            context: $context,
            instructions: $instructions,
            messages: $messages,
            tools: $tools,
            promptString: $promptString,
            attachments: $attachments
        );
    }
}
