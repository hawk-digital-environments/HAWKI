<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools\LaravelAi;

use App\Models\Ai\AiTool;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Tools\ToolAuthorization;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/** The SDK can invoke only this wrapper; tool internals retain their argument and resource policies. */
final readonly class AuthorizedTool implements Tool
{
    public function __construct(private Tool $tool, private AiTool $record, private AgentRequestContext $context)
    {
    }

    public function authorize(): void
    {
        $state = app(ToolExecutionState::class);
        $state->check($this->context);
        try {
            app(ToolAuthorization::class)->authorizeTool($this->record, $this->context);
        } catch (\App\Services\Ai\Tools\Exceptions\ToolAccessException $exception) {
            $state->deny($this->context, $exception);
        }
    }

    public function name(): string
    {
        return $this->record->name;
    }

    public function description(): Stringable|string
    {
        return $this->tool->description();
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->tool->schema($schema);
    }

    public function handle(Request $request): Stringable|string
    {
        $this->authorize();
        return $this->tool->handle($request);
    }
}
