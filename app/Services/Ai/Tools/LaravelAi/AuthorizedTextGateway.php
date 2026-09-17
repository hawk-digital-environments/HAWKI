<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools\LaravelAi;

use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Access\ModelAuthorization;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\TextGenerationOptions;

/**
 * Rechecks authorization on every SDK loop step before any provider request.
 */
final readonly class AuthorizedTextGateway implements StepTextGateway
{
    public function __construct(
        private StepTextGateway $gateway,
        private ?AgentRequestContext $context = null,
    ) {
    }

    public function withContext(AgentRequestContext $context): self
    {
        return $this->context === $context ? $this : new self($this->gateway, $context);
    }

    public function generateTextStep(TextProvider $provider, string $model, ?string $instructions, array $messages, array $tools, ?array $schema, ?TextGenerationOptions $options, ?int $timeout, StepContext $stepContext): StepResponse
    {
        $this->authorize($tools);

        return $this->gateway->generateTextStep($provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout, $stepContext);
    }

    public function generateStreamStep(string $invocationId, TextProvider $provider, string $model, ?string $instructions, array $messages, array $tools, ?array $schema, ?TextGenerationOptions $options, ?int $timeout, StepContext $stepContext): \Generator
    {
        $this->authorize($tools);
        return yield from $this->gateway->generateStreamStep($invocationId, $provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout, $stepContext);
    }

    private function authorize(array $tools): void
    {
        if ($this->context) {
            app(ModelAuthorization::class)->authorize($this->context);
        }

        app(NativeToolAuthorizations::class)->authorize($tools);
    }
}
