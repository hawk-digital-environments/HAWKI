<?php
declare(strict_types=1);

namespace Tests\Feature\Services\Ai\Agents\ChatAgentAssistantPayloadTransformationTestFixtures;

use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\FakeTextGateway;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\TextGenerationOptions;

/**
 * Fake text gateway that records the fully assembled request exactly as it
 * crosses the provider boundary — model, system instructions, and the complete
 * message list — before producing the canned fake response.
 *
 * Installed onto the provider driver via {@see \Laravel\Ai\Providers\Concerns\HasTextGateway::useTextGateway()},
 * it replaces the HTTP-speaking gateway while the real agent pipeline
 * (factory payload rewrite → message assembly → instruction wrapping) still
 * runs, so tests can assert on the transformed values without any network.
 */
class CapturingTextGateway extends FakeTextGateway
{
    /**
     * Recorded steps in order of generation.
     *
     * @var list<array{model: string, instructions: string|null, messages: array}>
     */
    public array $capturedSteps = [];

    public function generateTextStep(
        TextProvider $provider,
        string       $model,
        ?string      $instructions,
        array        $messages,
        array        $tools,
        ?array       $schema,
        ?TextGenerationOptions $options,
        ?int         $timeout,
        StepContext  $stepContext,
    ): StepResponse
    {
        $this->capturedSteps[] = [
            'model' => $model,
            'instructions' => $instructions,
            'messages' => $messages,
        ];

        return parent::generateTextStep(
            $provider,
            $model,
            $instructions,
            $messages,
            $tools,
            $schema,
            $options,
            $timeout,
            $stepContext,
        );
    }
}
