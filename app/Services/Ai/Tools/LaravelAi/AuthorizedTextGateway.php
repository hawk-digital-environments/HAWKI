<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools\LaravelAi;

use Generator;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\TextGenerationOptions;

/** Rechecks authorization on every SDK loop step before any provider request. */
final readonly class AuthorizedTextGateway implements StepTextGateway
{
    public function __construct(private StepTextGateway $gateway)
    {
    }

    public function generateTextStep(TextProvider $provider, string $model, ?string $instructions, array $messages, array $tools, ?array $schema, ?TextGenerationOptions $options, ?int $timeout, StepContext $stepContext): StepResponse
    {
        app(NativeToolAuthorizations::class)->authorize($tools);
        return $this->gateway->generateTextStep($provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout, $stepContext);
    }

    public function generateStreamStep(string $invocationId, TextProvider $provider, string $model, ?string $instructions, array $messages, array $tools, ?array $schema, ?TextGenerationOptions $options, ?int $timeout, StepContext $stepContext): Generator
    {
        app(NativeToolAuthorizations::class)->authorize($tools);
        return yield from $this->gateway->generateStreamStep($invocationId, $provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout, $stepContext);
    }
}
