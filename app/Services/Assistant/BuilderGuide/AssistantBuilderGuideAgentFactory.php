<?php
declare(strict_types=1);


namespace App\Services\Assistant\BuilderGuide;


use App\Models\Ai\AiModel;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Agents\Implementations\AbstractAgentFactory;
use Laravel\Ai\Messages\Message;

/**
 * Builds the {@see AssistantBuilderGuideAgent}. Deliberately not declared in
 * the {@see \App\Services\Ai\Agents\AgentRegistry}: the guide is only ever run
 * by {@see AssistantBuilderGuideService}, never for a regular chat request.
 */
class AssistantBuilderGuideAgentFactory extends AbstractAgentFactory
{
    /**
     * @param array{model: AiModel, instructions: string, messages: list<Message>, categoryIds: list<string>, modelIds: list<string>, settingOptions: array<string, list<string>>, avatarBackgrounds?: list<string>} $request
     */
    public function createAgent(mixed $request): AgentInterface|null
    {
        return new AssistantBuilderGuideAgent(
            context: $this->createRequestContext($request['model']),
            instructions: $request['instructions'],
            messages: $request['messages'],
            categoryIds: $request['categoryIds'],
            modelIds: $request['modelIds'],
            settingOptions: $request['settingOptions'],
            avatarBackgrounds: $request['avatarBackgrounds'] ?? [],
        );
    }
}
