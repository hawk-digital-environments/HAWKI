<?php

declare(strict_types=1);

namespace App\Services\Ai\Agents\Implementations\Chat;

use App\Services\Ai\Agents\Adapters\AbstractTextGeneratingAgent;
use App\Services\Ai\Agents\Values\AgentRequestContext;

/**
 * The generic chat agent for ordinary (unstructured) exchanges.
 *
 * Structured-output requests use {@see StructuredChatAgent} instead: the SDK rejects
 * streaming for any agent implementing {@see \Laravel\Ai\Contracts\HasStructuredOutput},
 * so the interface must be carried by a dedicated class that only the (non-streaming,
 * parse-enforced) json_schema path instantiates.
 */
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
