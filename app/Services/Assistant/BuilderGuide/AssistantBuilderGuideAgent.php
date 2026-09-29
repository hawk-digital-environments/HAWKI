<?php
declare(strict_types=1);


namespace App\Services\Assistant\BuilderGuide;


use App\Services\Ai\Agents\Adapters\AbstractTextGeneratingAgent;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * The assistant builder's guide: a conversational agent that walks the creator
 * through setting up an assistant. Every turn answers with a chat reply plus
 * the builder fields it wants to fill, so the frontend can apply them to the
 * draft directly.
 *
 * Every property is required (nullable where "no change" is valid) so the
 * schema also holds under providers that enforce strict structured output.
 * Category and model are enums of what the creator may actually pick, so a
 * provider that enforces the schema cannot invent one.
 */
class AssistantBuilderGuideAgent extends AbstractTextGeneratingAgent implements HasStructuredOutput
{
    /**
     * @param list<string> $categoryIds
     * @param list<string> $modelIds
     */
    public function __construct(
        AgentRequestContext $context,
        string $instructions,
        array $messages,
        private readonly array $categoryIds,
        private readonly array $modelIds,
    ) {
        parent::__construct(context: $context, instructions: $instructions, messages: $messages, tools: []);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reply' => $schema->string()
                ->description('The message shown to the creator in the chat. Markdown is allowed.')
                ->required(),
            'updates' => $schema->object([
                'name' => $schema->string()
                    ->description('Short assistant name, at most 255 characters. null to keep the current value.')
                    ->nullable()
                    ->required(),
                'handle' => $schema->string()
                    ->description('Unique @handle users mention the assistant with: lowercase letters, digits and hyphens, derived from the name. null to keep the current value.')
                    ->nullable()
                    ->required(),
                'categoryId' => $schema->string()
                    ->enum([...$this->categoryIds, null])
                    ->description('Id of the best-fitting category. null to keep the current value.')
                    ->nullable()
                    ->required(),
                'model' => $schema->string()
                    ->enum([...$this->modelIds, null])
                    ->description('model_id of the model the assistant runs on. null to keep the current value.')
                    ->nullable()
                    ->required(),
                'description' => $schema->string()
                    ->description('One-sentence summary shown on the assistant card. null to keep the current value.')
                    ->nullable()
                    ->required(),
                'detailDescription' => $schema->string()
                    ->description('Longer description for the assistant detail page. null to keep the current value.')
                    ->nullable()
                    ->required(),
                'systemPrompt' => $schema->string()
                    ->description('The complete system prompt (instructions) of the assistant, replacing the current one. null to keep the current value.')
                    ->nullable()
                    ->required(),
                'greeting' => $schema->string()
                    ->description('First message the assistant greets its users with. null to keep the current value.')
                    ->nullable()
                    ->required(),
                'starterPrompts' => $schema->array()
                    ->items($schema->string())
                    ->max(AssistantBuilderGuideService::MAX_STARTER_PROMPTS)
                    ->description('The complete list of suggested opening prompts for users, replacing the current list. null to keep the current value.')
                    ->nullable()
                    ->required(),
            ])->required(),
        ];
    }
}
